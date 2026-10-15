<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDomainTest extends TestCase
{
    private ?string $adminDomain = 'admin.diskartech.world';

    public function createApplication()
    {
        // Route domains are read at bootstrap, just as in a Render deployment.
        $previousEnv = $_ENV;
        $previousServer = $_SERVER;
        $_ENV['ADMIN_DOMAIN'] = $_SERVER['ADMIN_DOMAIN'] = $this->adminDomain ?? '';
        try {
            return parent::createApplication();
        } finally {
            $_ENV = $previousEnv;
            $_SERVER = $previousServer;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://diskartech.world',
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'session.domain' => null,
            'session.secure' => true,
        ]);
    }

    public function test_public_landing_links_to_the_admin_host(): void
    {
        foreach (['diskartech.world', 'diskartech-backend.onrender.com'] as $host) {
            $this->get('https://'.$host.'/')->assertOk()->assertViewIs('landing')
                ->assertSee('href="https://admin.diskartech.world/admin/login"', false);
        }
    }

    public function test_admin_home_and_protected_pages_require_sign_in(): void
    {
        $this->get('https://admin.diskartech.world/')
            ->assertRedirect('https://admin.diskartech.world/admin/login');
        $this->get('https://admin.diskartech.world/admin/account')
            ->assertRedirect('https://admin.diskartech.world/login');
        $this->getJson('https://admin.diskartech.world/admin/account')->assertUnauthorized();
    }

    public function test_login_form_posts_on_admin_host_and_links_back_to_public_landing(): void
    {
        $this->get('https://admin.diskartech.world/admin/login')->assertOk()
            ->assertSee('action="https://admin.diskartech.world/admin/login"', false)
            ->assertSee('href="https://diskartech.world"', false);
    }

    public function test_old_admin_bookmarks_redirect_and_preserve_filters(): void
    {
        foreach (['diskartech.world', 'diskartech-backend.onrender.com'] as $host) {
            $this->get('https://'.$host.'/admin/reports?status=pending&page=2')
                ->assertRedirect('https://admin.diskartech.world/admin/reports?status=pending&page=2');
            $this->get('https://'.$host.'/login')
                ->assertRedirect('https://admin.diskartech.world/admin/login');
        }
        $this->get('https://admin.diskartech.world/admin/not-a-page')->assertNotFound();
    }

    public function test_admin_domain_login_aliases_share_throttling(): void
    {
        $invalid = ['email' => 'admin@example.com', 'password' => ['invalid']];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('https://admin.diskartech.world'.($i % 2 ? '/login' : '/admin/login'), $invalid)
                ->assertUnprocessable();
        }
        $this->post('https://admin.diskartech.world/admin/login', $invalid)
            ->assertStatus(429)->assertViewIs('admin.auth.login')
            ->assertSee('action="https://admin.diskartech.world/admin/login"', false);
    }

    public function test_admin_write_requests_are_not_available_on_public_or_render_hosts(): void
    {
        foreach (['diskartech.world', 'diskartech-backend.onrender.com'] as $host) {
            $this->post('https://'.$host.'/admin/login', [])->assertStatus(405);
            $this->post('https://'.$host.'/login', [])->assertStatus(405);
            $this->patch('https://'.$host.'/admin/account', [])->assertStatus(405);
        }
    }

    public function test_login_protected_page_and_logout_work_on_the_admin_host(): void
    {
        (require database_path('migrations/2026_08_14_092718_create_admins_table.php'))->up();
        (require database_path('migrations/2026_10_11_180000_add_active_session_to_admins_table.php'))->up();
        Admin::create([
            'name' => 'Test Admin', 'email' => 'admin@example.com',
            'password' => Hash::make('Example-password1!'),
        ]);

        $response = $this->post('https://admin.diskartech.world/admin/login', [
            'email' => 'admin@example.com', 'password' => 'Example-password1!',
        ])->assertRedirect('https://admin.diskartech.world/admin/dashboard');
        $this->assertAuthenticated('admin');
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertEmpty($cookie->getDomain(), 'Admin cookies stay on the admin hostname.');
            $this->assertTrue($cookie->isSecure());
        }
        $this->get('https://admin.diskartech.world/admin/settings')->assertOk();
        $this->get('https://admin.diskartech.world/')
            ->assertRedirect('https://admin.diskartech.world/admin/dashboard');
        $this->get('https://admin.diskartech.world/admin/login')
            ->assertRedirect('https://admin.diskartech.world/admin/dashboard');
        $this->get('https://diskartech.world/')->assertOk()->assertViewIs('landing');
        $this->post('https://admin.diskartech.world/admin/logout')
            ->assertRedirect('https://admin.diskartech.world/admin/login');
        $this->assertGuest('admin');
        $this->assertNull(Admin::first()->active_session_token);
    }

    public function test_local_admin_still_works_when_no_admin_domain_is_configured(): void
    {
        $this->adminDomain = null;
        $this->refreshApplication();
        config(['session.driver' => 'array']);

        $this->get('http://localhost/')->assertOk()->assertViewIs('landing');
        $this->get('http://localhost/admin/login')->assertOk();
        $this->get('http://localhost/admin/account')->assertRedirect('http://localhost/login');
    }
}
