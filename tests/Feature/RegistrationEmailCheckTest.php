<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationEmailCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array']);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('registered_with_google')->default(false);
            $table->boolean('isEmailVerified')->default(false);
        });
        (require database_path('migrations/2026_08_14_025124_create_personal_access_tokens_table.php'))->up();
    }

    public function test_existing_accounts_across_roles_are_detected_without_issuing_a_session_or_exposing_details(): void
    {
        foreach (['student', 'employer', 'household', 'admin'] as $role) {
            $user = User::create(['email' => $role.'@example.com', 'role' => $role, 'password' => Hash::make('Test-password1!')]);
            $this->postJson('/api/register/check-email', ['email' => $user->email])->assertOk()
                ->assertExactJson(['status' => 'success', 'exists' => true])->assertHeader('Cache-Control', 'no-store, private');
            $this->assertFalse($user->fresh()->isEmailVerified);
            $this->assertTrue(Hash::check('Test-password1!', $user->fresh()->password));
        }
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->assertSame(4, User::count());
    }

    public function test_lookup_normalizes_whitespace_and_case_and_handles_unknown_emails(): void
    {
        User::create(['email' => 'Student@Example.com', 'role' => 'student', 'password' => Hash::make('Test-password1!'), 'registered_with_google' => true]);
        $this->postJson('/api/register/check-email', ['email' => ' STUDENT@EXAMPLE.COM '])->assertOk()->assertJsonPath('exists', true);
        $this->postJson('/api/register/check-email', ['email' => 'new@example.com'])->assertOk()->assertExactJson(['status' => 'success', 'exists' => false]);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_invalid_email_inputs_are_rejected(): void
    {
        foreach ([null, '', '<script>', ['email@example.com'], "x' OR 1=1", str_repeat('a', 255).'@example.com', "test\x00@example.com"] as $email) {
            $this->postJson('/api/register/check-email', ['email' => $email])->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(0, User::count());
    }

    public function test_public_check_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 15; $i++) $this->postJson('/api/register/check-email', ['email' => 'new@example.com'])->assertOk();
        $this->postJson('/api/register/check-email', ['email' => 'new@example.com'])->assertStatus(429);
    }
}
