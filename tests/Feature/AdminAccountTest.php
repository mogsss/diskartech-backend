<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('admins', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->unique(); $table->string('password'); $table->rememberToken(); $table->timestamps();
        });
        $this->admin = Admin::create(['name' => 'Test Admin', 'email' => 'admin@example.com', 'password' => Hash::make('Original-password1!')]);
        $this->actingAs($this->admin, 'admin');
    }

    public function test_account_renders_current_admin_and_both_sidebar_links_without_credentials(): void
    {
        $response = $this->get('/admin/account')->assertOk()->assertSee('Test Admin')->assertSee('admin@example.com')
            ->assertSee(route('admin.reviews'))->assertSee(route('admin.account'))->assertSee('Change password')
            ->assertDontSee($this->admin->password, false)->assertDontSee('Original-password1!', false);
        if ($path = getenv('DISKARTECH_ACCOUNT_PREVIEW_PATH')) file_put_contents($path, $response->getContent());
    }

    public function test_name_update_is_normalized_and_cannot_modify_another_admin(): void
    {
        $other = Admin::create(['name' => 'Other Admin', 'email' => 'other@example.com', 'password' => Hash::make('Other-password1!')]);
        $this->patch('/admin/account', [
            'id' => $other->id, 'name' => " \x00Updated Admin ", 'email' => 'admin@example.com', 'password' => 'Ignored-password1!',
        ])->assertRedirect(route('admin.account'))->assertSessionHas('success');
        $this->assertSame('Updated Admin', $this->admin->fresh()->name);
        $this->assertSame('Other Admin', $other->fresh()->name);
        $this->assertTrue(Hash::check('Original-password1!', $this->admin->fresh()->password));
    }

    public function test_email_change_requires_correct_current_password_and_normalizes_email(): void
    {
        foreach ([null, 'Wrong-password1!'] as $password) {
            $this->from('/admin/account')->patch('/admin/account', [
                'name' => 'Test Admin', 'email' => 'NEW@EXAMPLE.COM', 'current_password' => $password,
            ])->assertSessionHasErrors(['current_password'], null, 'profile');
        }
        $this->assertSame('admin@example.com', $this->admin->fresh()->email);
        $this->patch('/admin/account', [
            'name' => 'Test Admin', 'email' => ' NEW@EXAMPLE.COM ', 'current_password' => 'Original-password1!',
        ])->assertRedirect(route('admin.account'))->assertSessionHas('success');
        $this->assertSame('new@example.com', $this->admin->fresh()->email);
    }

    public function test_profile_rejects_markup_invalid_email_and_duplicates(): void
    {
        Admin::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => Hash::make('Other-password1!')]);
        foreach (['<b>Name</b>', '&lt;script&gt;bad&lt;/script&gt;', '   ', str_repeat('a', 101)] as $name) {
            $this->patch('/admin/account', ['name' => $name, 'email' => 'admin@example.com'])
                ->assertSessionHasErrors(['name'], null, 'profile');
        }
        foreach (['invalid', 'other@example.com'] as $email) {
            $this->patch('/admin/account', ['name' => 'Test Admin', 'email' => $email, 'current_password' => 'Original-password1!'])
                ->assertSessionHasErrors(['email'], null, 'profile');
        }
        $this->assertSame('Test Admin', $this->admin->fresh()->name);
        $this->assertSame('admin@example.com', $this->admin->fresh()->email);
    }

    public function test_password_change_requires_correct_password_confirmation_and_minimum_length(): void
    {
        foreach ([
            ['current_password' => 'Wrong-password1!', 'password' => 'New-password1!', 'password_confirmation' => 'New-password1!'],
            ['current_password' => 'Original-password1!', 'password' => 'short', 'password_confirmation' => 'short'],
            ['current_password' => 'Original-password1!', 'password' => 'New-password1!', 'password_confirmation' => 'Different-password1!'],
            ['current_password' => 'Original-password1!', 'password' => 'Original-password1!', 'password_confirmation' => 'Original-password1!'],
        ] as $data) {
            $this->from('/admin/account')->put('/admin/account/password', $data)->assertSessionHasErrors(null, null, 'password');
            $this->assertTrue(Hash::check('Original-password1!', $this->admin->fresh()->password));
        }
        $this->assertNull(session('_old_input.current_password'));
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    public function test_password_is_hashed_without_plain_text_sanitization_and_remember_token_is_rotated(): void
    {
        $this->admin->forceFill(['remember_token' => 'old-test-token'])->save();
        $password = '<New-password1!>';
        $this->put('/admin/account/password', [
            'current_password' => 'Original-password1!', 'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('admin.account'))->assertSessionHas('success');
        $fresh = $this->admin->fresh();
        $this->assertTrue(Hash::check($password, $fresh->password));
        $this->assertNotSame($password, $fresh->password);
        $this->assertNull($fresh->remember_token);
        $this->assertAuthenticatedAs($this->admin, 'admin');
    }

    public function test_account_requires_admin_authentication(): void
    {
        auth('admin')->logout();
        $this->get('/admin/account')->assertRedirect(route('login'));
        $this->patch('/admin/account', ['name' => 'Changed'])->assertRedirect(route('login'));
        $this->put('/admin/account/password', [])->assertRedirect(route('login'));
    }
}
