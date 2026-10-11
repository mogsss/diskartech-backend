<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AdminSession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminSessionTest extends TestCase
{
    private Admin $admin;
    private array $browsers = [];
    private array $ownerCookies = [];
    private const PASSWORD = 'Original-password1!';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'session.driver' => 'database', 'session.connection' => 'sqlite',
            'session.lifetime' => 120, 'session.lottery' => [0, 100],
        ]);
        Carbon::setTestNow(Carbon::parse('2026-10-11 08:00:00', 'UTC'));
        (require database_path('migrations/2026_08_14_092718_create_admins_table.php'))->up();
        (require database_path('migrations/2026_10_11_180000_add_active_session_to_admins_table.php'))->up();
        (require database_path('migrations/2026_10_11_190000_create_sessions_table.php'))->up();
        $this->admin = Admin::create(['name' => 'Example Admin', 'email' => 'admin@example.com', 'password' => Hash::make(self::PASSWORD)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // Real, independent database sessions; keep the ownership middleware enabled.
    private function browser(string $browser, string $method, string $uri, array $data = [], bool $json = false): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('redirect');
        $this->unencryptedCookies = [];
        if (($this->ownerCookies[$browser]['expires'] ?? 0) > now()->getTimestamp()) {
            // The response cookie is already encrypted by the web middleware.
            $this->withUnencryptedCookie(AdminSession::COOKIE_NAME, $this->ownerCookies[$browser]['value']);
        }
        $this->withCookie(config('session.cookie'), $this->browsers[$browser] ?? Str::random(40));
        $this->withHeader('Accept', $json ? 'application/json' : 'text/html');
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest(), [], $this->transformHeadersToServerVars($this->defaultHeaders));
        $this->browsers[$browser] = $this->app['session.store']->getId();
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() !== AdminSession::COOKIE_NAME) continue;
            if ($cookie->getExpiresTime() <= now()->getTimestamp()) {
                unset($this->ownerCookies[$browser]);
            } else {
                $this->ownerCookies[$browser] = ['value' => $cookie->getValue(), 'expires' => $cookie->getExpiresTime()];
            }
        }
        return $response;
    }

    private function login(string $browser, ?Admin $admin = null, string $uri = '/admin/login'): TestResponse
    {
        return $this->browser($browser, 'POST', $uri, ['email' => ($admin ?? $this->admin)->email, 'password' => self::PASSWORD]);
    }

    public function test_reopening_the_same_browser_returns_to_dashboard_without_another_device_error(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $owner = $this->admin->fresh()->active_session_token;
        $this->browser('first', 'GET', '/admin/login')->assertRedirect('/admin/dashboard')->assertSessionHasNoErrors();
        $this->login('first', uri: '/login')->assertRedirect('/admin/dashboard')->assertSessionHasNoErrors();
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
    }

    public function test_same_browser_can_reauthenticate_after_server_session_is_lost(): void
    {
        $response = $this->login('first')->assertRedirect('/admin/dashboard');
        $owner = $this->admin->fresh()->active_session_token;
        $token = session(AdminSession::SESSION_KEY);
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === AdminSession::COOKIE_NAME);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertStringNotContainsString($token, $cookie->getValue());
        DB::table('sessions')->where('id', $this->browsers['first'])->delete();

        $this->login('second')->assertSessionHasErrors('email');
        $this->login('first')->assertRedirect('/admin/dashboard')->assertSessionHasNoErrors();
        $this->assertNotSame($owner, $this->admin->fresh()->active_session_token);
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
        $this->login('second')->assertSessionHasErrors('email');
    }

    public function test_file_session_loss_does_not_block_the_owning_browser(): void
    {
        $directory = storage_path('framework/testing/admin-session-'.Str::uuid());
        mkdir($directory, 0777, true);
        config(['session.driver' => 'file', 'session.files' => $directory]);
        try {
            $this->login('first')->assertRedirect('/admin/dashboard');
            unlink($directory.'/'.$this->browsers['first']);
            $this->login('second')->assertSessionHasErrors('email');
            $this->login('first')->assertRedirect('/admin/dashboard')->assertSessionHasNoErrors();
            $this->browser('first', 'GET', '/admin/settings')->assertOk();
        } finally {
            foreach (glob($directory.'/*') as $file) unlink($file);
            rmdir($directory);
        }
    }

    public function test_missing_session_ownership_token_is_restored_from_authenticated_cookie(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $owner = $this->admin->fresh()->active_session_token;
        $row = DB::table('sessions')->where('id', $this->browsers['first'])->first();
        $payload = unserialize(base64_decode($row->payload));
        unset($payload[AdminSession::SESSION_KEY]);
        DB::table('sessions')->where('id', $row->id)->update(['payload' => base64_encode(serialize($payload))]);
        $this->browser('first', 'GET', '/admin/settings')->assertOk()->assertSessionHas(AdminSession::SESSION_KEY);
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $this->login('second')->assertSessionHasErrors('email');
    }

    public function test_recovery_requires_correct_credentials_and_does_not_disclose_ownership_on_failure(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $owner = $this->admin->fresh()->active_session_token;
        DB::table('sessions')->where('id', $this->browsers['first'])->delete();
        $this->browser('first', 'POST', '/admin/login', ['email' => $this->admin->email, 'password' => 'Wrong-password1!'])
            ->assertSessionHasErrors('email')->assertSessionHas('errors', fn ($errors) => $errors->first('email') === 'The provided credentials do not match our records.');
        $this->assertGuest('admin');
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $this->login('first')->assertRedirect('/admin/dashboard');
    }

    public function test_tampered_cookie_cannot_claim_another_browsers_login(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $owner = $this->admin->fresh()->active_session_token;
        $this->ownerCookies['second'] = ['value' => 'tampered-cookie', 'expires' => now()->addHour()->getTimestamp()];
        $this->login('second')->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
    }

    public function test_old_recovery_cookie_cannot_replace_a_new_active_browser(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $this->ownerCookies['first']['expires'] = now()->addHours(4)->getTimestamp();
        $this->travel(120)->minutes();
        $this->login('second')->assertRedirect('/admin/dashboard');
        $owner = $this->admin->fresh()->active_session_token;
        DB::table('sessions')->where('id', $this->browsers['first'])->delete();
        $this->login('first')->assertSessionHasErrors('email');
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $this->browser('second', 'GET', '/admin/settings')->assertOk();
    }

    public function test_new_browser_is_blocked_and_first_browser_remains_signed_in(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin, 'admin');
        $owner = $this->admin->fresh()->active_session_token;
        $this->login('second')->assertRedirect()->assertSessionHasErrors('email')->assertSessionHas('errors', fn ($errors) => str_contains($errors->first('email'), 'already signed in'));
        $this->assertGuest('admin');
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $this->assertNull(session('_old_input.password'));
        $this->browser('second', 'GET', '/admin/settings')->assertRedirect(route('login'));
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
        $this->assertAuthenticatedAs($this->admin, 'admin');
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
    }

    public function test_wrong_credentials_do_not_claim_or_disclose_an_active_login(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $owner = $this->admin->fresh()->active_session_token;
        $this->browser('second', 'POST', '/admin/login', ['email' => $this->admin->email, 'password' => 'Wrong-password1!'])
            ->assertSessionHasErrors('email')->assertSessionHas('errors', fn ($errors) => $errors->first('email') === 'The provided credentials do not match our records.');
        $this->assertGuest('admin');
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $other = Admin::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => Hash::make(self::PASSWORD)]);
        $this->browser('third', 'POST', '/admin/login', ['email' => $other->email, 'password' => 'Wrong-password1!'])->assertSessionHasErrors('email');
        $this->assertNull($other->fresh()->active_session_token);
    }

    public function test_logout_releases_account_for_the_next_browser_and_alias_is_also_protected(): void
    {
        $this->login('first', uri: '/login')->assertRedirect('/admin/dashboard');
        $this->login('second', uri: '/login')->assertSessionHasErrors('email');
        $this->browser('first', 'POST', '/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
        $this->assertArrayNotHasKey('first', $this->ownerCookies);
        $this->assertNull($this->admin->fresh()->active_session_token);
        $this->assertNull($this->admin->fresh()->active_session_expires_at);
        $this->login('second')->assertRedirect('/admin/dashboard');
        $this->browser('second', 'GET', '/admin/settings')->assertOk();
        $this->login('first')->assertSessionHasErrors('email');
    }

    public function test_idle_expiration_allows_new_login_without_old_browser_reviving_or_releasing_it(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $oldOwner = $this->admin->fresh()->active_session_token;
        $this->travel(120)->minutes();
        $this->login('second')->assertRedirect('/admin/dashboard');
        $newOwner = $this->admin->fresh()->active_session_token;
        $this->assertNotSame($oldOwner, $newOwner);
        $this->browser('first', 'POST', '/admin/logout')->assertRedirect(route('admin.login'))->assertSessionHasErrors('email');
        $this->assertSame($newOwner, $this->admin->fresh()->active_session_token);
        $this->browser('second', 'GET', '/admin/settings')->assertOk();
    }

    public function test_activity_extends_expiration_and_expired_session_is_rejected_with_json(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $initialExpiry = $this->admin->fresh()->active_session_expires_at;
        $this->travel(119)->minutes();
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
        $this->assertGreaterThan($initialExpiry, $this->admin->fresh()->active_session_expires_at);
        $this->travel(2)->minutes();
        $this->login('second')->assertSessionHasErrors('email');
        $this->travel(119)->minutes();
        $this->browser('first', 'POST', '/admin/account/check-email', ['email' => 'new@example.com'], true)->assertStatus(401)->assertJsonStructure(['message']);
        $this->assertGuest('admin');
        $this->login('second')->assertRedirect('/admin/dashboard');
    }

    public function test_different_admin_accounts_can_sign_in_simultaneously(): void
    {
        $other = Admin::create(['name' => 'Other Admin', 'email' => 'other@example.com', 'password' => Hash::make(self::PASSWORD)]);
        $this->login('first')->assertRedirect('/admin/dashboard');
        $this->login('second', $other)->assertRedirect('/admin/dashboard');
        $this->browser('first', 'GET', '/admin/account')->assertOk()->assertSee('Example Admin');
        $this->browser('second', 'GET', '/admin/account')->assertOk()->assertSee('Other Admin');
    }

    public function test_password_change_keeps_ownership_after_session_id_rotation(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $before = $this->browsers['first'];
        $owner = $this->admin->fresh()->active_session_token;
        $this->browser('first', 'PUT', '/admin/account/password', [
            'current_password' => self::PASSWORD, 'password' => '<Updated-password2!>', 'password_confirmation' => '<Updated-password2!>',
        ])->assertRedirect(route('admin.account'))->assertSessionHas('success');
        $this->assertNotSame($before, $this->browsers['first']);
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $this->browser('first', 'GET', '/admin/account')->assertOk();
        $this->browser('second', 'POST', '/admin/login', ['email' => $this->admin->email, 'password' => '<Updated-password2!>'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_existing_sessions_are_adopted_once_after_deployment(): void
    {
        $guardKey = Auth::guard('admin')->getName();
        foreach (['first', 'second'] as $browser) {
            $this->browsers[$browser] = Str::random(40);
            DB::table('sessions')->insert([
                'id' => $this->browsers[$browser], 'last_activity' => now()->getTimestamp(),
                'payload' => base64_encode(serialize([$guardKey => $this->admin->id, '_token' => Str::random(40)])),
            ]);
        }
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
        $owner = $this->admin->fresh()->active_session_token;
        $this->assertNotNull($owner);
        $this->browser('second', 'GET', '/admin/settings')->assertRedirect(route('admin.login'))->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->assertSame($owner, $this->admin->fresh()->active_session_token);
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
    }

    public function test_atomic_ownership_and_stale_tokens_cannot_refresh_or_release_new_owner(): void
    {
        $sessions = app(AdminSession::class);
        $this->assertTrue($sessions->claim($this->admin, 'first-token'));
        $this->assertFalse($sessions->claim($this->admin, 'second-token'));
        $this->assertTrue($sessions->refresh($this->admin, 'first-token'));
        $this->travel(120)->minutes();
        $this->assertFalse($sessions->refresh($this->admin, 'first-token'));
        $this->assertTrue($sessions->claim($this->admin, 'second-token'));
        $this->assertFalse($sessions->refresh($this->admin, 'first-token'));
        $sessions->release($this->admin, 'first-token');
        $this->assertSame(hash('sha256', 'second-token'), $this->admin->fresh()->active_session_token);
        $this->assertArrayNotHasKey('active_session_token', $this->admin->fresh()->toArray());
        $this->assertArrayNotHasKey('active_session_expires_at', $this->admin->fresh()->toArray());
    }

    public function test_login_validation_preserves_credentials_and_rejects_malformed_inputs(): void
    {
        foreach ([['email' => ['bad'], 'password' => self::PASSWORD], ['email' => $this->admin->email, 'password' => ['bad']], ['email' => 'bad', 'password' => self::PASSWORD]] as $data) {
            $this->browser('first', 'POST', '/admin/login', $data)->assertSessionHasErrors();
            $this->assertNull($this->admin->fresh()->active_session_token);
        }
        $password = ' <Exact-password1!> ';
        $this->admin->forceFill(['password' => Hash::make($password)])->save();
        $this->browser('first', 'POST', '/admin/login', ['email' => ' '.$this->admin->email.' ', 'password' => $password])->assertRedirect('/admin/dashboard');
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
    }

    public function test_migration_rolls_back_without_deleting_admins(): void
    {
        $migration = require database_path('migrations/2026_10_11_180000_add_active_session_to_admins_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('admins', 'active_session_token'));
        $this->assertSame(1, Admin::count());
        $migration->up();
        $this->assertTrue(Schema::hasColumn('admins', 'active_session_token'));
        $this->assertNull($this->admin->fresh()->active_session_token);
    }

    public function test_sessions_migration_preserves_an_existing_session_table_and_active_login(): void
    {
        $this->login('first')->assertRedirect('/admin/dashboard');
        $id = $this->browsers['first'];
        $payload = DB::table('sessions')->where('id', $id)->value('payload');
        $migration = require database_path('migrations/2026_10_11_190000_create_sessions_table.php');
        $migration->up();
        $migration->down();
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertSame($payload, DB::table('sessions')->where('id', $id)->value('payload'));
        $this->browser('first', 'GET', '/admin/settings')->assertOk();
    }
}
