<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'services.gmail.client_id' => 'test-client',
            'services.gmail.client_secret' => 'test-secret',
            'services.gmail.refresh_token' => 'test-refresh',
            'services.gmail.from_address' => 'diskartech.official@gmail.com',
            'services.gmail.timeout' => 10,
            'services.otp.resend_cooldown' => 60,
        ]);
        Http::preventStrayRequests();
        Log::spy();
        $this->travelTo(now()->startOfSecond());

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('isEmailVerified')->default(false);
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function user(array $attributes = []): User
    {
        $user = User::create(array_merge([
            'email' => 'registrant@example.com',
            'password' => 'test-password',
            'role' => 'student',
        ], $attributes));
        Sanctum::actingAs($user);

        return $user;
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'sent-message-id']),
        ]);
    }

    public function test_sends_to_another_registrant_and_enforces_cooldown_with_cached_authorization(): void
    {
        $user = $this->user();
        $this->fakeSuccess();

        $this->postJson('/api/email/send-otp')->assertOk()
            ->assertJsonPath('mail_sent', true)->assertJsonPath('retry_after', 60);
        $user->refresh();
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $user->otp_code);
        $this->assertTrue($user->otp_expires_at->equalTo(now()->addMinutes(10)));
        Http::assertSent(function ($request) use ($user) {
            if (! str_contains($request->url(), 'messages/send')) {
                return false;
            }
            $mime = base64_decode(strtr($request['raw'], '-_', '+/'));

            return $request->hasHeader('Authorization', 'Bearer test-access')
                && str_contains($mime, 'To: registrant@example.com')
                && str_contains($mime, 'From: DiskarTech <diskartech.official@gmail.com>')
                && str_contains($mime, $user->otp_code);
        });
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'test-refresh');

        $originalCode = $user->otp_code;
        $this->postJson('/api/email/send-otp')->assertStatus(429)
            ->assertHeader('Retry-After', '60')->assertJsonPath('retry_after', 60);
        $this->assertSame($originalCode, $user->fresh()->otp_code);
        Http::assertSentCount(2);

        $this->travel(60)->seconds();
        $this->postJson('/api/email/send-otp')->assertOk();
        Http::assertSentCount(3); // The second message reuses the access token.
    }

    public function test_cooldown_is_per_user(): void
    {
        $this->fakeSuccess();
        $this->user();
        $this->postJson('/api/email/send-otp')->assertOk();
        $this->user(['email' => 'another@example.com']);
        $this->postJson('/api/email/send-otp')->assertOk();
        Http::assertSentCount(3);
    }

    public function test_overlapping_sends_are_blocked(): void
    {
        $user = $this->user();
        $lock = Cache::lock('otp:send:'.$user->id, 30);
        $lock->get();
        try {
            $this->postJson('/api/email/send-otp')->assertStatus(429)->assertJsonPath('mail_sent', false);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public static function providerFailures(): array
    {
        return [
            'authorization rejected' => [400, 200, ['id' => 'unused']],
            'quota exhausted' => [200, 429, ['error' => 'quota']],
            'malformed success' => [200, 200, []],
            'access token rejected' => [200, 401, ['error' => 'unauthorized']],
        ];
    }

    #[DataProvider('providerFailures')]
    public function test_provider_failures_preserve_previous_code_and_do_not_report_success(int $tokenStatus, int $sendStatus, array $body): void
    {
        $user = $this->user(['otp_code' => '654321', 'otp_expires_at' => now()->addMinutes(8)]);
        $previousExpiry = $user->otp_expires_at->toISOString();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()
                ->push(['access_token' => 'test-access', 'expires_in' => 3600], $tokenStatus)
                ->push(['access_token' => 'test-access', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::sequence()->push($body, $sendStatus)->push(['id' => 'sent-message-id']),
        ]);

        $this->postJson('/api/email/send-otp')->assertStatus(503)
            ->assertJsonPath('status', 'error')->assertJsonPath('mail_sent', false);
        $this->assertSame('654321', $user->fresh()->otp_code);
        $this->assertSame($previousExpiry, $user->fresh()->otp_expires_at->toISOString());
        if ($sendStatus === 401 && $tokenStatus === 200) {
            $this->postJson('/api/email/send-otp')->assertOk();
            Http::assertSentCount(4); // Rejected token must be refreshed on the next attempt.
        }
    }

    public function test_timeout_preserves_code_and_releases_send_lock(): void
    {
        $user = $this->user(['otp_code' => '654321', 'otp_expires_at' => now()->addMinutes(8)]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::sequence()->pushFailedConnection()->push(['id' => 'sent-message-id']),
        ]);
        $this->postJson('/api/email/send-otp')->assertStatus(503)->assertJsonPath('mail_sent', false);
        $this->assertSame('654321', $user->fresh()->otp_code);
        $this->postJson('/api/email/send-otp')->assertOk();
    }

    public function test_missing_credentials_and_already_verified_users_do_not_send(): void
    {
        $user = $this->user();
        config(['services.gmail.refresh_token' => null]);
        $this->postJson('/api/email/send-otp')->assertStatus(503)->assertJsonPath('mail_sent', false);
        $this->assertNull($user->fresh()->otp_code);
        $user->update(['isEmailVerified' => true]);
        $this->postJson('/api/email/send-otp')->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_verifies_only_the_saved_unexpired_code_and_clears_it(): void
    {
        $user = $this->user(['otp_code' => '654321', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->postJson('/api/email/verify-otp', ['otp_code' => '123456'])->assertStatus(400);
        $this->assertFalse($user->fresh()->isEmailVerified);
        $this->postJson('/api/email/verify-otp', ['otp_code' => '654321'])->assertOk();
        $this->assertTrue($user->fresh()->isEmailVerified);
        $this->assertNull($user->fresh()->otp_code);
        $this->postJson('/api/email/verify-otp', ['otp_code' => '654321'])->assertStatus(400);
    }

    public function test_expired_missing_expiry_and_non_digit_codes_cannot_verify(): void
    {
        $user = $this->user(['otp_code' => '654321', 'otp_expires_at' => now()]);
        $this->postJson('/api/email/verify-otp', ['otp_code' => '654321'])->assertStatus(400);
        $user->update(['otp_expires_at' => null]);
        $this->postJson('/api/email/verify-otp', ['otp_code' => '654321'])->assertStatus(400);
        $this->postJson('/api/email/verify-otp', ['otp_code' => 'abcdef'])->assertStatus(422);
        $this->assertFalse($user->fresh()->isEmailVerified);
    }

    public function test_verification_attempts_are_limited_independently_of_sending(): void
    {
        $this->user();
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/email/verify-otp', ['otp_code' => '999999'])->assertStatus(400);
        }
        $this->postJson('/api/email/verify-otp', ['otp_code' => '999999'])->assertStatus(429);
        $this->fakeSuccess();
        $this->postJson('/api/email/send-otp')->assertOk();
    }

    public function test_otp_routes_require_authentication(): void
    {
        $this->postJson('/api/email/send-otp')->assertUnauthorized();
        $this->postJson('/api/email/verify-otp', ['otp_code' => '654321'])->assertUnauthorized();
        Http::assertNothingSent();
    }
}
