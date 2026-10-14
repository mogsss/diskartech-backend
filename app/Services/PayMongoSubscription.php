<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayMongoSubscription
{
    public const PRICES = ['monthly' => 14900, 'annually' => 149000];

    private function key(): string
    {
        $key = trim((string) config('services.paymongo.secret_key'));
        abort_unless(str_starts_with($key, 'sk_test_') && strlen($key) > 8, 503, 'PayMongo test checkout is not configured.');
        return $key;
    }

    public function methods(): array
    {
        $methods = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) config('services.paymongo.payment_methods', 'qrph'))))));
        abort_unless($methods && !array_diff($methods, ['qrph', 'gcash', 'paymaya', 'card']), 503, 'Payment methods are not configured.');
        return $methods;
    }

    public function current(Employer $employer): array
    {
        $expires = $employer->subscription_expires_at ? CarbonImmutable::parse($employer->subscription_expires_at, 'UTC') : null;
        $quota = app(EmployerJobQuota::class);
        return ['plan' => $quota->plan($employer), 'expires_at' => $expires?->toIso8601String(), 'test_mode' => true, 'prices' => self::PRICES,
            'job_quota' => $quota->usage($employer->user, $employer)];
    }

    private function request(string $method, string $path, ?array $data = null, ?string $idempotency = null): array
    {
        $request = Http::withBasicAuth($this->key(), '')->acceptJson()->asJson()->connectTimeout(5)->timeout(20);
        if ($idempotency) $request = $request->withHeaders(['Idempotency-Key' => $idempotency]);
        try {
            $response = $method === 'POST' ? $request->post('https://api.paymongo.com'.$path, $data) : $request->get('https://api.paymongo.com'.$path);
        } catch (ConnectionException) {
            abort(503, 'Could not reach the payment provider. Please try again.');
        }
        abort_unless($response->successful() && is_array($response->json('data')), 503, 'Payment provider unavailable. Please check your payment settings or try again.');
        return $response->json('data');
    }

    public function create(User $user, string $cycle, string $requestId): SubscriptionPayment
    {
        $this->key();
        $methods = $this->methods();
        [$order, $create] = DB::transaction(function () use ($user, $cycle, $requestId, $methods) {
            Employer::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $order = SubscriptionPayment::where('user_id', $user->id)->where('request_id', $requestId)->first();
            if (!$order) $order = SubscriptionPayment::where('user_id', $user->id)->whereIn('status', ['creating', 'pending'])->latest()->first();
            if ($order) {
                abort_unless($order->billing_cycle === $cycle, 409, 'Finish your existing checkout before changing the billing cycle.');
                if ($order->status === 'paid' || $order->checkout_session_id) return [$order, false];
                abort_if($order->status === 'expired', 409, 'This checkout has expired. Start a new checkout.');
                abort_if($order->status === 'creating' && $order->updated_at->greaterThan(now()->subSeconds(30)), 409, 'Your checkout is being prepared. Please check again shortly.');
                abort_if($order->created_at->lessThan(now()->subHours(23)), 409, 'This checkout needs payment support before retrying.');
                $order->update(['status' => 'creating']);
                return [$order, true];
            }
            return [SubscriptionPayment::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'request_id' => $requestId, 'billing_cycle' => $cycle, 'amount' => self::PRICES[$cycle], 'currency' => 'PHP', 'payment_methods' => $methods, 'billing_email' => $user->email]), true];
        });
        if (!$create) return $order;
        try {
            $session = $this->request('POST', '/v2/checkout_sessions', ['data' => ['attributes' => [
                'line_items' => [['name' => 'DiskarTech Premium — '.($cycle === 'monthly' ? '1 month' : '1 year'), 'amount' => $order->amount, 'currency' => 'PHP', 'quantity' => 1]],
                'payment_method_types' => $order->payment_methods,
                'billing' => ['email' => $order->billing_email],
                'reference_number' => $order->id,
                'metadata' => ['subscription_payment_id' => $order->id, 'user_id' => (string) $order->user_id],
                'success_url' => route('subscription.return', ['outcome' => 'success']),
                'cancel_url' => route('subscription.return', ['outcome' => 'cancel']),
                'send_email_receipt' => true, 'show_line_items' => true, 'show_description' => true, 'pass_on_fees' => false,
                'description' => 'One-time Premium purchase. No automatic renewal.',
            ]]], 'subscription-'.$order->id);
            $url = $session['attributes']['checkout_url'] ?? null;
            abort_unless(is_string($session['id'] ?? null) && preg_match('/^cs_[A-Za-z0-9]+$/', $session['id']) && is_string($url) && $this->validUrl($url) && ($session['attributes']['livemode'] ?? null) === false, 503, 'Could not prepare a test checkout.');
            DB::transaction(function () use ($order, $session, $url) {
                $locked = SubscriptionPayment::lockForUpdate()->findOrFail($order->id);
                abort_if($locked->checkout_session_id && $locked->checkout_session_id !== $session['id'], 503, 'Checkout reference mismatch.');
                $locked->checkout_session_id = $session['id'];
                $locked->checkout_url = $url;
                if ($locked->status === 'creating') $locked->status = 'pending';
                $locked->save();
            });
        } finally {
            SubscriptionPayment::whereKey($order->id)->where('status', 'creating')->update(['status' => 'pending']);
        }
        return $order->fresh();
    }

    private function validUrl(string $url): bool
    {
        $parts = parse_url($url);
        return $parts && ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'checkout.paymongo.com' && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']);
    }

    public function verify(SubscriptionPayment $order, ?string $sessionId = null): SubscriptionPayment
    {
        if ($order->status === 'paid') return $order;
        $id = $sessionId ?? $order->checkout_session_id;
        if (!$id) return $order;
        abort_unless(preg_match('/^cs_[A-Za-z0-9]+$/', $id), 422, 'Invalid checkout reference.');
        $session = $this->request('GET', '/v1/checkout_sessions/'.$id);
        $attributes = $session['attributes'] ?? [];
        abort_unless(($session['id'] ?? null) === $id && ($attributes['reference_number'] ?? null) === $order->id
            && ($attributes['livemode'] ?? null) === false
            && (string) ($attributes['metadata']['user_id'] ?? '') === (string) $order->user_id
            && ($attributes['metadata']['subscription_payment_id'] ?? null) === $order->id, 422, 'Payment details do not match this checkout.');
        $paid = null;
        foreach ($attributes['payments'] ?? [] as $payment) {
            $details = $payment['attributes'] ?? [];
            if (($details['status'] ?? '') !== 'paid') continue;
            abort_unless(is_string($payment['id'] ?? null) && preg_match('/^pay_[A-Za-z0-9]+$/', $payment['id'])
                && ($details['amount'] ?? null) === $order->amount && ($details['currency'] ?? '') === 'PHP'
                && ($details['livemode'] ?? false) === false && empty($details['disputed']) && empty($details['refunds']), 422, 'Payment amount or currency does not match this checkout.');
            $paid = $payment;
            break;
        }
        return DB::transaction(function () use ($order, $paid, $id, $attributes) {
            // Use the same lock order for checkout creation and fulfillment.
            $employer = Employer::where('user_id', $order->user_id)->lockForUpdate()->firstOrFail();
            $locked = SubscriptionPayment::lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'paid') return $locked;
            abort_if($locked->checkout_session_id && $locked->checkout_session_id !== $id, 422, 'Checkout reference mismatch.');
            $locked->checkout_session_id = $id;
            if ($paid) {
                $paidAt = $paid['attributes']['paid_at'] ?? null;
                $start = is_int($paidAt) ? CarbonImmutable::createFromTimestampUTC($paidAt) : CarbonImmutable::now('UTC');
                abort_if($start->greaterThan(now()->addMinutes(5)) || $start->lessThan($locked->created_at->copy()->subMinutes(5)), 422, 'Payment date does not match this checkout.');
                $existing = $employer->subscription_expires_at ? CarbonImmutable::parse($employer->subscription_expires_at, 'UTC') : null;
                $base = (bool) $employer->isSubscribed && $existing && $existing->greaterThan($start) ? $existing : $start;
                $until = $locked->billing_cycle === 'monthly' ? $base->addMonthNoOverflow() : $base->addYearNoOverflow();
                $employer->isSubscribed = true;
                $employer->subscription_plan = 'premium';
                $employer->subscription_started_at = $start;
                $employer->subscription_expires_at = $until;
                $employer->save();
                $locked->status = 'paid'; $locked->payment_id = $paid['id']; $locked->paid_at = $start; $locked->granted_until = $until;
            } elseif (($attributes['status'] ?? '') === 'expired') $locked->status = 'expired';
            else $locked->status = 'pending';
            $locked->save();
            return $locked;
        });
    }
}
