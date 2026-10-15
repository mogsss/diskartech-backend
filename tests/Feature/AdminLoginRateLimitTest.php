<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminLoginRateLimitTest extends TestCase
{
    private const PASSWORD = 'Admin-password1!';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'cache.limiter' => 'array', 'session.driver' => 'array',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(now()->startOfSecond());
        (require database_path('migrations/2026_08_14_092718_create_admins_table.php'))->up();
        (require database_path('migrations/2026_10_11_180000_add_active_session_to_admins_table.php'))->up();
        Admin::create(['name' => 'Test Admin', 'email' => 'admin@example.com', 'password' => Hash::make(self::PASSWORD)]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.40']);
    }

    private function credentials(array $overrides = []): array
    {
        return array_replace(['email' => 'admin@example.com', 'password' => 'Wrong-password1!'], $overrides);
    }

    public function test_sixth_attempt_is_blocked_before_claiming_a_session_and_login_recovers(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->from('/admin/login')->post('/admin/login', $this->credentials())
                ->assertRedirect('/admin/login')->assertSessionHasErrors('email');
        }
        $this->post('/admin/login', $this->credentials(['password' => self::PASSWORD]))
            ->assertStatus(429)->assertViewIs('admin.auth.login')
            ->assertHeader('Retry-After', '60')->assertHeader('X-RateLimit-Limit', '5')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Too many sign-in attempts. Please try again in 60 seconds.')
            ->assertSee('value="admin@example.com"', false)->assertDontSee(self::PASSWORD);
        $this->assertGuest('admin');
        $this->assertNull(Admin::first()->active_session_token);
        $this->assertNull(session('_old_input.password'));

        $this->travel(20)->seconds();
        $this->post('/admin/login', $this->credentials())->assertStatus(429)
            ->assertHeader('Retry-After', '40')->assertSee('Please try again in 40 seconds.');
        $this->travel(41)->seconds();
        $this->post('/admin/login', $this->credentials(['password' => self::PASSWORD]))
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated('admin');
        $this->assertNotNull(Admin::first()->active_session_token);
    }

    public function test_login_aliases_share_the_same_allowance_and_get_pages_stay_accessible(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post($i % 2 ? '/login' : '/admin/login', $this->credentials())->assertRedirect();
        }
        foreach (['/login', '/admin/login'] as $url) {
            $this->post($url, $this->credentials())->assertStatus(429)->assertHeader('X-RateLimit-Limit', '5');
            $this->get($url)->assertOk();
        }
    }

    public function test_case_whitespace_and_control_character_variants_cannot_reset_the_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', $this->credentials())->assertRedirect();
        }
        foreach (['ADMIN@EXAMPLE.COM', ' admin@example.com ', "\tAdmin@Example.Com\n", "admin\u{202E}@example.com"] as $email) {
            $this->postJson('/admin/login', $this->credentials(['email' => $email]))
                ->assertStatus(429)->assertJsonPath('code', 'login_rate_limited');
        }
    }

    public function test_separate_emails_and_ips_have_independent_pair_limits(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', $this->credentials())->assertRedirect();
        }
        $this->post('/admin/login', $this->credentials(['email' => 'other@example.com']))
            ->assertRedirect()->assertSessionHasErrors('email');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.41']);
        $this->post('/admin/login', $this->credentials(['password' => self::PASSWORD]))
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated('admin');
    }

    public function test_rotating_unknown_emails_cannot_bypass_the_overall_ip_limit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/admin/login', $this->credentials(['email' => "unknown{$i}@example.com"]))
                ->assertRedirect()->assertSessionHasErrors('email');
        }
        $this->postJson('/admin/login', $this->credentials(['password' => self::PASSWORD]))
            ->assertStatus(429)->assertHeader('X-RateLimit-Limit', '30')
            ->assertJsonPath('retry_after', 60)->assertHeader('Retry-After', '60');
        $this->assertGuest('admin');
        $this->assertNull(Admin::first()->active_session_token);
    }

    public function test_malformed_payloads_are_limited_and_browser_email_output_is_escaped(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/admin/login', ['email' => ['invalid'], 'password' => ['invalid']])->assertUnprocessable();
        }
        $this->post('/admin/login', ['email' => ['invalid'], 'password' => 'secret'])
            ->assertStatus(429)->assertViewHas('loginEmail', '')->assertDontSee('value="Array"', false);

        $email = '"><script>alert(1)</script>@example.com';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/admin/login', $this->credentials(['email' => $email]))->assertUnprocessable();
        }
        $this->post('/admin/login', $this->credentials(['email' => $email]))
            ->assertStatus(429)->assertSee($email)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_successful_logins_and_logout_do_not_reset_the_allowance(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', $this->credentials(['password' => self::PASSWORD]))
                ->assertRedirect('/admin/dashboard');
            $this->post('/admin/logout')->assertRedirect('/admin/login');
            $this->assertGuest('admin');
        }
        $this->post('/admin/login', $this->credentials(['password' => self::PASSWORD]))->assertStatus(429);
        $this->assertGuest('admin');
        $this->assertNull(Admin::first()->active_session_token);
    }

    public function test_mobile_and_admin_login_counters_are_independent(): void
    {
        $invalid = ['email' => 'admin@example.com', 'password' => ['invalid']];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $invalid)->assertUnprocessable();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/admin/login', $invalid)->assertUnprocessable();
        }
        $this->postJson('/admin/login', $invalid)->assertStatus(429)->assertJsonPath('retry_after', 60);
        $this->postJson('/api/login', $invalid)->assertStatus(429)->assertJsonPath('retry_after', 60);
    }
}
