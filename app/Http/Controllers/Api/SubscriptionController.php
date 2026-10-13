<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\SubscriptionPayment;
use App\Services\PayMongoSubscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    private function employer(Request $request): Employer
    {
        abort_unless($request->user()->role === 'employer', 403, 'Subscriptions are available to employer accounts.');
        return Employer::where('user_id', $request->user()->id)->firstOrFail();
    }

    private function response(Employer $employer, PayMongoSubscription $service, ?SubscriptionPayment $payment = null)
    {
        return response()->json(['subscription' => $service->current($employer->fresh()), 'payment' => $payment?->summary()])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, PayMongoSubscription $service)
    {
        $employer = $this->employer($request);
        $pending = SubscriptionPayment::where('user_id', $request->user()->id)->whereIn('status', ['creating', 'pending'])->latest()->first();
        return $this->response($employer, $service, $pending);
    }

    public function store(Request $request, PayMongoSubscription $service)
    {
        $employer = $this->employer($request);
        $data = $request->validate(['billing_cycle' => 'required|in:monthly,annually', 'request_id' => 'required|uuid']);
        $payment = $service->create($request->user(), $data['billing_cycle'], $data['request_id']);
        return $this->response($employer, $service, $payment);
    }

    public function verify(Request $request, string $payment, PayMongoSubscription $service)
    {
        $employer = $this->employer($request);
        $order = SubscriptionPayment::where('user_id', $request->user()->id)->findOrFail($payment);
        return $this->response($employer, $service, $service->verify($order));
    }

    public function webhook(Request $request, PayMongoSubscription $service)
    {
        $secret = trim((string) config('services.paymongo.webhook_secret'));
        abort_unless($secret !== '', 503, 'Payment webhook is not configured.');
        $raw = $request->getContent();
        abort_if(strlen($raw) > 262144, 413);
        $parts = [];
        foreach (explode(',', (string) $request->header('Paymongo-Signature')) as $part) {
            $pair = explode('=', trim($part), 2);
            abort_unless(count($pair) === 2 && !array_key_exists($pair[0], $parts), 401, 'Invalid payment signature.');
            $parts[$pair[0]] = $pair[1];
        }
        abort_unless(preg_match('/^\d{10}$/', $parts['t'] ?? '') && abs(time() - (int) $parts['t']) <= 300
            && preg_match('/^[a-f0-9]{64}$/', $parts['te'] ?? '')
            && hash_equals(hash_hmac('sha256', $parts['t'].'.'.$raw, $secret), $parts['te']), 401, 'Invalid payment signature.');
        $payload = json_decode($raw, true);
        abort_unless(is_array($payload), 422, 'Invalid payment event.');
        // PayMongo uses both the Event resource and newer hosted-checkout envelopes.
        $event = $payload['data']['attributes'] ?? $payload['data'] ?? [];
        if (($event['type'] ?? '') !== 'checkout_session.payment.paid') return response()->json(['received' => true]);
        abort_unless(($event['livemode'] ?? null) === false, 422, 'Only test payment events are accepted.');
        $session = $event['data'] ?? [];
        $id = $session['id'] ?? '';
        abort_unless(is_string($id) && preg_match('/^cs_[A-Za-z0-9]+$/', $id), 422, 'Invalid checkout reference.');
        $reference = $session['attributes']['reference_number'] ?? null;
        $order = SubscriptionPayment::where('checkout_session_id', $id)->first();
        if (!$order && is_string($reference)) $order = SubscriptionPayment::find($reference);
        if ($order) $service->verify($order, $id);
        return response()->json(['received' => true]);
    }

    public function returned(Request $request)
    {
        return response()->view('subscription-return', ['cancelled' => $request->query('outcome') === 'cancel'])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
}
