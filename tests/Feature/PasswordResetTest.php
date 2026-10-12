<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GmailOtpMailer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private string $code = '';
    private const ORIGINAL = 'Original-password1!';
    private const UPDATED = 'Updated-password2!';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array']);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('registered_with_google')->default(false);
            $table->boolean('isEmailVerified')->default(true);
            $table->string('otp_code')->nullable(); $table->timestamp('otp_expires_at')->nullable();
        });
        (require database_path('migrations/2026_10_12_140000_create_mobile_password_resets_table.php'))->up();
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique();
            $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        $this->mock(GmailOtpMailer::class, function ($mock) {
            $mock->shouldReceive('sendPasswordResetCode')->andReturnUsing(function ($email, $code) { $this->code = $code; });
        });
    }

    private function account(string $role = 'student', bool $google = false): User
    {
        return User::create(['email' => $role.'@example.com', 'role' => $role, 'password' => Hash::make(self::ORIGINAL), 'registered_with_google' => $google, 'isEmailVerified' => true]);
    }

    private function grant(User $user): string
    {
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        return $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $this->code])->assertOk()->json('reset_token');
    }

    private function payload(User $user, string $token, string $password = self::UPDATED): array
    {
        return ['email' => $user->email, 'reset_token' => $token, 'password' => $password, 'password_confirmation' => $password];
    }

    public function test_all_mobile_roles_can_recover_without_a_login_and_revoke_all_sessions(): void
    {
        foreach (['student', 'employer', 'household'] as $role) {
            $user = $this->account($role);
            $user->createToken('phone'); $user->createToken('other-phone');
            $user->forceFill(['otp_code' => '654321', 'otp_expires_at' => now()->addMinutes(5)])->save();
            $token = $this->grant($user);
            $stored = DB::table('mobile_password_resets')->where('email', $user->email)->first();
            $this->assertNull($stored->code_hash);
            $this->assertSame(hash('sha256', $token), $stored->reset_token_hash);
            $this->postJson('/api/password/reset', $this->payload($user, $token))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertTrue(Hash::check(self::UPDATED, $user->fresh()->password));
            $this->assertSame(0, $user->tokens()->count());
            $this->assertSame('654321', $user->fresh()->otp_code);
            $this->assertDatabaseMissing('mobile_password_resets', ['email' => $user->email]);
            $this->postJson('/api/password/reset', $this->payload($user, $token))->assertUnprocessable()->assertJsonValidationErrors('reset_token');
        }
    }

    public function test_google_admin_and_missing_accounts_receive_the_same_send_response(): void
    {
        $google = $this->account('student', true); $admin = $this->account('admin');
        $expected = null;
        foreach ([$google->email, $admin->email, 'missing@example.com'] as $email) {
            $response = $this->postJson('/api/password/forgot', ['email' => $email])->assertOk();
            $expected ??= $response->json();
            $this->assertSame($expected, $response->json());
        }
        $this->assertSame('', $this->code);
        $this->assertSame(0, DB::table('mobile_password_resets')->count());
        $this->postJson('/api/password/verify-code', ['email' => $google->email, 'code' => '123456'])->assertUnprocessable();
    }

    public function test_invalid_inputs_are_rejected_and_email_case_is_normalized(): void
    {
        $user = $this->account();
        $this->postJson('/api/password/forgot', ['email' => '<script>'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/password/forgot', ['email' => ' STUDENT@EXAMPLE.COM '])->assertOk();
        $reset = DB::table('mobile_password_resets')->first();
        $this->assertSame($user->id, $reset->user_id);
        $this->assertNotSame($this->code, $reset->code_hash);
        $this->assertSame(hash_hmac('sha256', $this->code, config('app.key')), $reset->code_hash);
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => '<123>'])->assertUnprocessable();
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $this->code])->assertOk();
    }

    public function test_five_wrong_codes_lock_the_challenge_even_when_the_correct_code_is_later_entered(): void
    {
        $user = $this->account();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => '000000'])->assertUnprocessable();
        $this->assertSame(5, DB::table('mobile_password_resets')->first()->attempts);
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $this->code])->assertUnprocessable();
        $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
    }

    public function test_otp_expiry_and_single_use_are_enforced(): void
    {
        $user = $this->account();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        $this->travel(10)->minutes();
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $this->code])->assertUnprocessable();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $this->code])->assertOk();
        $this->postJson('/api/password/verify-code', ['email' => $user->email, 'code' => $this->code])->assertUnprocessable();
    }

    public function test_reset_requires_a_verified_unexpired_token_bound_to_the_same_account(): void
    {
        $user = $this->account(); $other = $this->account('household');
        $token = $this->grant($user);
        $this->postJson('/api/password/reset', $this->payload($other, $token))->assertUnprocessable()->assertJsonValidationErrors('reset_token');
        $this->postJson('/api/password/reset', $this->payload($user, str_repeat('a', 64)))->assertUnprocessable();
        $this->travel(10)->minutes();
        $this->postJson('/api/password/reset', $this->payload($user, $token))->assertUnprocessable();
        $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
        $this->assertTrue(Hash::check(self::ORIGINAL, $other->fresh()->password));
    }

    public function test_password_rules_confirmation_reuse_and_exact_credentials_are_enforced(): void
    {
        $user = $this->account(); $token = $this->grant($user);
        foreach (['Short1!', 'lowercase1!', 'Uppercase!', 'Uppercase12', self::ORIGINAL, str_repeat('é', 34).'Ab1!xx', "Updated1!\x00"] as $password) {
            $this->postJson('/api/password/reset', $this->payload($user, $token, $password))->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $payload = $this->payload($user, $token); $payload['password_confirmation'] = 'Mismatch1!';
        $this->postJson('/api/password/reset', $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/password/reset', $this->payload($user, $token, ' <Updated-password2!> '))->assertOk();
        $this->assertTrue(Hash::check(' <Updated-password2!> ', $user->fresh()->password));
    }

    public function test_resending_invalidates_the_previous_reset_grant_and_limits_requests(): void
    {
        $user = $this->account(); $token = $this->grant($user);
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertStatus(429)->assertJsonPath('retry_after', 60);
        $this->travel(61)->seconds();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        $this->postJson('/api/password/reset', $this->payload($user, $token))->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertOk();
        $this->travel(61)->seconds();
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertStatus(429);
    }

    public function test_mail_failure_does_not_create_a_usable_challenge(): void
    {
        $user = $this->account();
        $this->mock(GmailOtpMailer::class, fn ($mock) => $mock->shouldReceive('sendPasswordResetCode')->andThrow(new \RuntimeException('Test delivery failure')));
        $this->postJson('/api/password/forgot', ['email' => $user->email])->assertStatus(503);
        $this->assertSame(0, DB::table('mobile_password_resets')->count());
        $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
    }

    public function test_verification_attempts_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) $this->postJson('/api/password/verify-code', ['email' => 'missing@example.com', 'code' => '000000'])->assertUnprocessable();
        $this->postJson('/api/password/verify-code', ['email' => 'missing@example.com', 'code' => '000000'])->assertStatus(429);
    }

    public function test_reset_mail_uses_the_existing_gmail_delivery_with_a_reset_subject_and_expiry(): void
    {
        config(['services.gmail' => ['client_id' => 'test', 'client_secret' => 'test', 'refresh_token' => 'test', 'from_address' => 'sender@example.com', 'timeout' => 10]]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'test-message']),
        ]);
        (new GmailOtpMailer)->sendPasswordResetCode('student@example.com', '123456');
        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'messages/send')) return false;
            $raw = base64_decode(strtr($request['raw'], '-_', '+/'));
            return str_contains($raw, 'Reset Your Password | DiskarTech') && str_contains($raw, 'reset your DiskarTech password') && str_contains($raw, '10 minutes');
        });
    }
}
