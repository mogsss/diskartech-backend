<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    private const PASSWORD = 'Login-password1!';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(now()->startOfSecond());
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('email')->unique(); $table->string('password'); $table->string('role');
            $table->boolean('isEmailVerified')->default(true); $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique();
            $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        User::create(['email' => 'student@example.com', 'role' => 'student', 'password' => Hash::make(self::PASSWORD)]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
    }

    private function credentials(array $overrides = []): array
    {
        return array_replace(['email' => 'student@example.com', 'password' => 'Wrong-password1!'], $overrides);
    }

    public function test_sixth_attempt_is_blocked_before_a_token_can_be_issued_and_login_recovers(): void
    {
        $hash = User::first()->password;
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/login', $this->credentials())->assertUnauthorized();
        $blocked = $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))
            ->assertStatus(429)->assertJsonPath('code', 'login_rate_limited')
            ->assertHeader('Retry-After', '60')->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonMissingPath('token');
        $this->assertSame(60, $blocked->json('retry_after'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame($hash, User::first()->password);

        $this->travel(20)->seconds();
        $this->postJson('/api/login', $this->credentials())->assertStatus(429)
            ->assertHeader('Retry-After', '40')->assertJsonPath('retry_after', 40);
        $this->travel(41)->seconds();
        $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))->assertOk()
            ->assertJsonPath('status', 'success')->assertJsonStructure(['token']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_case_and_whitespace_changes_cannot_reset_the_email_ip_bucket(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/login', $this->credentials())->assertUnauthorized();
        foreach (['STUDENT@EXAMPLE.COM', ' student@example.com ', "\tStudent@Example.Com\n"] as $email) {
            $this->postJson('/api/login', $this->credentials(['email' => $email]))->assertStatus(429);
        }
    }

    public function test_separate_emails_and_ips_have_separate_pair_limits(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/login', $this->credentials())->assertUnauthorized();
        $this->postJson('/api/login', $this->credentials(['email' => 'another@example.com']))->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);
        $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))->assertOk();
    }

    public function test_rotating_emails_cannot_bypass_the_total_ip_limit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/login', $this->credentials(['email' => "unknown{$i}@example.com"]))->assertNotFound();
        }
        $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))
            ->assertStatus(429)->assertHeader('X-RateLimit-Limit', '30')->assertJsonMissingPath('token');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.12']);
        $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))->assertOk();
    }

    public function test_malformed_emails_are_throttled_without_causing_server_errors(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/login', ['email' => ['invalid'], 'password' => 'bad'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => ['invalid'], 'password' => 'bad'])->assertStatus(429);
    }

    public function test_successful_logins_also_count_so_the_limit_cannot_be_reset_with_valid_credentials(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))->assertOk();
        $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))->assertStatus(429);
        $this->assertDatabaseCount('personal_access_tokens', 5);
    }

    public function test_google_login_has_its_own_ip_limit_that_expires(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/google-login', ['email' => "unknown{$i}@example.com"])->assertNotFound();
        }
        $this->postJson('/api/google-login', ['email' => 'student@example.com'])->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '10')->assertJsonPath('retry_after', 60)->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        // Password login keeps its own allowance even on the same network.
        $this->postJson('/api/login', $this->credentials(['password' => self::PASSWORD]))->assertOk();
        $this->travel(61)->seconds();
        $this->postJson('/api/google-login', ['email' => 'unknown@example.com'])->assertNotFound();
    }

    public function test_google_limit_is_separate_for_other_ips(): void
    {
        for ($i = 0; $i < 10; $i++) $this->postJson('/api/google-login', ['email' => 'unknown@example.com'])->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.13']);
        $this->postJson('/api/google-login', ['email' => 'unknown@example.com'])->assertNotFound();
    }

    public function test_throttle_returns_json_even_without_an_accept_json_header(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/login', $this->credentials())->assertUnauthorized();
        $this->post('/api/login', $this->credentials(), ['Accept' => 'text/html'])->assertStatus(429)
            ->assertHeader('Content-Type', 'application/json')->assertJsonPath('code', 'login_rate_limited');
    }
}
