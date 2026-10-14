<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployerSubscriptionTest extends TestCase
{
    private User $employer;
    private $provider;
    private const ROOT = '/api/employer/subscription';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array', 'session.driver' => 'array', 'services.paymongo.secret_key' => 'sk_test_fixture', 'services.paymongo.webhook_secret' => 'webhook-fixture', 'services.paymongo.payment_methods' => 'qrph']);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::create('employers', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id')->unique(); $table->timestamps(); });
        Schema::create('available_jobs', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); $table->string('status')->default('active'); });
        (require database_path('migrations/2026_10_13_120000_create_subscription_payments_table.php'))->up();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $this->assertNotNull($this->provider, 'Unexpected payment provider call.');
            $this->assertSame('api.paymongo.com', parse_url($request->url(), PHP_URL_HOST));
            return ($this->provider)($request);
        });
        $this->employer = User::create(['email' => 'employer@example.com', 'password' => 'fixture', 'role' => 'employer']);
        Employer::create(['user_id' => $this->employer->id]);
    }

    private function fakeCreate(): void
    {
        $this->provider = function ($request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://api.paymongo.com/v2/checkout_sessions', $request->url());
            $data = $request['data']['attributes'];
            $this->assertSame(1, $data['line_items'][0]['quantity']);
            $this->assertSame('PHP', $data['line_items'][0]['currency']);
            $this->assertFalse($data['pass_on_fees']);
            $this->assertSame(['qrph'], $data['payment_method_types']);
            $this->assertSame('subscription-'.$data['reference_number'], $request->header('Idempotency-Key')[0]);
            return Http::response(['data' => ['id' => 'cs_Test'.str_replace('-', '', $data['reference_number']), 'attributes' => ['livemode' => false, 'checkout_url' => 'https://checkout.paymongo.com/cs_Test']]]);
        };
    }

    private function create(string $cycle = 'monthly', ?string $requestId = null): SubscriptionPayment
    {
        Sanctum::actingAs($this->employer);
        $this->fakeCreate();
        $response = $this->postJson(self::ROOT.'/checkout', ['billing_cycle' => $cycle, 'request_id' => $requestId ?? (string) Str::uuid(), 'amount' => 1, 'isSubscribed' => true])->assertOk()->assertJsonPath('payment.status', 'pending')->assertJsonPath('subscription.plan', 'basic');
        return SubscriptionPayment::findOrFail($response->json('payment.id'));
    }

    private function providerSession(SubscriptionPayment $order, bool $paid = true): array
    {
        return ['data' => ['id' => $order->checkout_session_id, 'type' => 'checkout_session', 'attributes' => [
            'livemode' => false, 'reference_number' => $order->id,
            'metadata' => ['user_id' => (string) $order->user_id, 'subscription_payment_id' => $order->id], 'status' => 'active',
            'payments' => $paid ? [['id' => 'pay_Test'.str_replace('-', '', $order->id), 'attributes' => ['status' => 'paid', 'amount' => $order->amount, 'currency' => 'PHP', 'livemode' => false, 'paid_at' => now()->timestamp, 'refunds' => []]]] : [],
        ]]];
    }

    private function verify(SubscriptionPayment $order, array $session)
    {
        $this->provider = fn () => Http::response($session);
        return $this->postJson(self::ROOT.'/payments/'.$order->id.'/verify');
    }

    public function test_only_employers_can_use_checkout_and_prices_are_server_controlled(): void
    {
        $this->getJson(self::ROOT)->assertUnauthorized();
        foreach (['student', 'household'] as $role) {
            Sanctum::actingAs(User::create(['email' => $role.'@example.com', 'password' => 'fixture', 'role' => $role]));
            $this->getJson(self::ROOT)->assertForbidden();
            $this->postJson(self::ROOT.'/checkout', [])->assertForbidden();
        }
        $order = $this->create();
        $this->assertSame(14900, $order->amount);
        $this->assertFalse((bool) Employer::first()->isSubscribed);
        $this->getJson(self::ROOT)->assertOk()->assertJsonPath('payment.id', $order->id)->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson(self::ROOT.'/checkout', ['billing_cycle' => 'free', 'request_id' => 'bad'])->assertUnprocessable();
    }

    public function test_monthly_payment_activates_once_and_existing_pending_checkout_is_reused(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        $request = (string) Str::uuid();
        $order = $this->create('monthly', $request);
        foreach ([$request, (string) Str::uuid()] as $id) {
            $this->postJson(self::ROOT.'/checkout', ['billing_cycle' => 'monthly', 'request_id' => $id])->assertOk()->assertJsonPath('payment.id', $order->id);
        }
        Http::assertSentCount(1);
        $this->postJson(self::ROOT.'/checkout', ['billing_cycle' => 'annually', 'request_id' => (string) Str::uuid()])->assertConflict();
        $this->verify($order, $this->providerSession($order))->assertOk()->assertJsonPath('payment.status', 'paid')->assertJsonPath('subscription.plan', 'premium');
        $this->getJson(self::ROOT)->assertOk()->assertJsonPath('subscription.job_quota.active_job_limit', null)->assertJsonPath('subscription.job_quota.can_post_job', true);
        $expires = Employer::first()->subscription_expires_at;
        $this->assertSame('2026-11-13 10:00:00', $expires);
        $this->postJson(self::ROOT.'/payments/'.$order->id.'/verify')->assertOk();
        $this->assertSame($expires, Employer::first()->subscription_expires_at);
    }

    public function test_annual_renewal_extends_existing_expiry_and_expired_premium_displays_basic(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        $profile = Employer::first();
        $profile->isSubscribed = true; $profile->subscription_expires_at = now()->addMonth(); $profile->save();
        Sanctum::actingAs($this->employer); $this->fakeCreate();
        $response = $this->postJson(self::ROOT.'/checkout', ['billing_cycle' => 'annually', 'request_id' => (string) Str::uuid()])->assertOk();
        $order = SubscriptionPayment::findOrFail($response->json('payment.id'));
        $this->assertSame(149000, $order->amount);
        $this->verify($order, $this->providerSession($order))->assertOk()->assertJsonPath('subscription.expires_at', '2027-11-13T10:00:00+00:00');
        $this->travel(14)->months();
        $this->getJson(self::ROOT)->assertOk()->assertJsonPath('subscription.plan', 'basic');
    }

    public function test_pending_failed_or_expired_sessions_and_success_redirect_do_not_activate(): void
    {
        $order = $this->create();
        $this->get('/subscription/return?outcome=success')->assertOk()->assertSee('Check your payment in the app');
        $this->verify($order, $this->providerSession($order, false))->assertOk()->assertJsonPath('payment.status', 'pending');
        $session = $this->providerSession($order); $session['data']['attributes']['payments'][0]['attributes']['status'] = 'failed';
        $this->verify($order, $session)->assertOk()->assertJsonPath('subscription.plan', 'basic');
        $session = $this->providerSession($order, false); $session['data']['attributes']['status'] = 'expired';
        $this->verify($order, $session)->assertOk()->assertJsonPath('payment.status', 'expired');
        $this->assertFalse((bool) Employer::first()->isSubscribed);
    }

    public function test_wrong_owner_amount_currency_mode_or_metadata_cannot_activate(): void
    {
        $order = $this->create();
        $other = User::create(['email' => 'other@example.com', 'password' => 'fixture', 'role' => 'employer']); Employer::create(['user_id' => $other->id]);
        Sanctum::actingAs($other);
        $this->postJson(self::ROOT.'/payments/'.$order->id.'/verify')->assertNotFound();
        Sanctum::actingAs($this->employer);
        $cases = [
            ['data.id', 'cs_Wrong'], ['data.attributes.reference_number', (string) Str::uuid()], ['data.attributes.livemode', true],
            ['data.attributes.metadata.user_id', (string) $other->id], ['data.attributes.metadata.subscription_payment_id', 'wrong'],
            ['data.attributes.payments.0.attributes.amount', 1], ['data.attributes.payments.0.attributes.currency', 'USD'],
            ['data.attributes.payments.0.attributes.livemode', true], ['data.attributes.payments.0.attributes.disputed', true],
            ['data.attributes.payments.0.attributes.refunds', [['amount' => 14900]]],
        ];
        foreach ($cases as [$path, $value]) {
            $session = $this->providerSession($order); data_set($session, $path, $value);
            $this->verify($order, $session)->assertUnprocessable();
            $this->assertFalse((bool) Employer::where('user_id', $this->employer->id)->first()->isSubscribed);
        }
    }

    public function test_webhooks_need_fresh_test_signatures_and_reconcile_with_provider_once(): void
    {
        $order = $this->create();
        $session = $this->providerSession($order);
        $this->provider = fn () => Http::response($session);
        foreach ([false, true] as $modern) {
            $event = ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => $session['data']];
            $raw = json_encode(['data' => $modern ? $event : ['id' => 'evt_Test', 'attributes' => $event]]);
            $t = (string) time();
            $sig = hash_hmac('sha256', $t.'.'.$raw, 'webhook-fixture');
            $path = '/api/payments/paymongo/webhook';
            $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't='.$t.',te=bad,li='], $raw)->assertUnauthorized();
            $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't='.$t.',te='.$sig.',li='], $raw)->assertOk();
        }
        $this->assertSame('paid', $order->fresh()->status);
        $expires = Employer::first()->subscription_expires_at;
        $this->verify($order->fresh(), $session)->assertOk();
        $this->assertSame($expires, Employer::first()->subscription_expires_at);
        Http::assertSentCount(2); // One creation and one authoritative retrieval.
        $raw = json_encode(['data' => []]); $old = time() - 600;
        $signature = hash_hmac('sha256', $old.'.'.$raw, 'webhook-fixture');
        $this->call('POST', '/api/payments/paymongo/webhook', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t=$old,te=$signature,li="], $raw)->assertUnauthorized();
    }

    public function test_missing_or_live_keys_and_provider_failures_do_not_leak_secrets(): void
    {
        Sanctum::actingAs($this->employer);
        $input = ['billing_cycle' => 'monthly', 'request_id' => (string) Str::uuid()];
        foreach (['', 'sk_live_do_not_charge'] as $key) {
            config(['services.paymongo.secret_key' => $key]);
            $this->postJson(self::ROOT.'/checkout', $input)->assertStatus(503)->assertDontSee('sk_live_do_not_charge');
        }
        config(['services.paymongo.secret_key' => 'sk_test_fixture']);
        $this->provider = fn () => Http::response(['errors' => [['detail' => 'sk_test_private_payload']]], 401);
        $this->postJson(self::ROOT.'/checkout', $input)->assertStatus(503)->assertDontSee('sk_test_private_payload');
        $order = SubscriptionPayment::first(); $this->assertSame('pending', $order->status);
        $this->fakeCreate();
        $this->postJson(self::ROOT.'/checkout', $input)->assertOk()->assertJsonPath('payment.id', $order->id);
        $this->assertSame(1, SubscriptionPayment::count());
    }

    public function test_provider_timeout_preserves_retry_identity_and_invalid_urls_are_rejected(): void
    {
        Sanctum::actingAs($this->employer);
        $input = ['billing_cycle' => 'monthly', 'request_id' => (string) Str::uuid()];
        $this->provider = fn () => throw new ConnectionException('private fixture');
        $this->postJson(self::ROOT.'/checkout', $input)->assertStatus(503)->assertDontSee('private fixture');
        $id = SubscriptionPayment::first()->id;
        $this->provider = fn () => Http::response(['data' => ['id' => 'cs_Test', 'attributes' => ['livemode' => false, 'checkout_url' => 'https://checkout.paymongo.com.evil.example/pay']]]);
        $this->postJson(self::ROOT.'/checkout', $input)->assertStatus(503);
        $this->fakeCreate();
        $this->postJson(self::ROOT.'/checkout', $input)->assertOk()->assertJsonPath('payment.id', $id);
        $this->assertSame(1, SubscriptionPayment::count());
    }

    public function test_migration_is_repeatable_and_no_overflow_monthly_duration_is_used(): void
    {
        (require database_path('migrations/2026_10_13_120000_create_subscription_payments_table.php'))->up();
        $this->travelTo(now()->setDate(2027, 1, 31)->setTime(10, 0));
        $order = $this->create();
        $this->verify($order, $this->providerSession($order))->assertOk()->assertJsonPath('subscription.expires_at', '2027-02-28T10:00:00+00:00');
    }
}
