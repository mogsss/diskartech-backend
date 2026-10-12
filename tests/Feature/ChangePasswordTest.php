<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    private const ORIGINAL = 'Original-password1!';
    private const UPDATED = 'Updated-password2!';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array']);
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('email')->unique(); $table->string('password'); $table->string('role');
            $table->boolean('isEmailVerified')->default(true); $table->boolean('registered_with_google')->default(false); $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique();
            $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
    }

    private function account(string $role = 'student'): User
    {
        return User::create(['email' => $role.'@example.com', 'role' => $role, 'password' => Hash::make(self::ORIGINAL)]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['current_password' => self::ORIGINAL, 'password' => self::UPDATED, 'password_confirmation' => self::UPDATED], $overrides);
    }

    public function test_password_change_requires_authentication(): void
    {
        $this->getJson('/api/user/account')->assertUnauthorized();
        $this->putJson('/api/user/password', $this->payload())->assertUnauthorized();
    }

    public function test_registration_rejects_invalid_google_source_flags(): void
    {
        foreach (['student', 'employer', 'household'] as $role) {
            $this->postJson('/api/register/'.$role, [
                'first_name' => 'Test', 'last_name' => 'Account', 'email' => $role.'@example.com',
                'password' => self::ORIGINAL, 'phone' => '09123456789',
                'school_name' => 'Test School', 'course' => 'BSIT', 'year_level' => '4th Year',
                'business_name' => 'Test Business', 'business_type' => 'Services', 'registered_with_google' => 'google',
            ])->assertUnprocessable()->assertJsonValidationErrors('registered_with_google');
        }
        $this->assertSame(0, User::count());
    }

    public function test_google_registration_disables_password_changes_for_all_mobile_roles(): void
    {
        foreach (['student', 'employer', 'household'] as $role) {
            $user = $this->account($role);
            $user->update(['registered_with_google' => true]);
            $token = $user->createToken('phone', ['*', 'auth:password'])->plainTextToken;
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson('/api/user/account')->assertOk()
                ->assertJsonPath('account.registered_with_google', true)->assertJsonPath('account.can_change_password', false);
            $this->withToken($token)->putJson('/api/user/password', $this->payload())->assertForbidden()->assertJsonPath('code', 'google_authentication');
            $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
        }
    }

    public function test_google_session_does_not_permanently_disable_a_manually_registered_account(): void
    {
        $user = $this->account();
        $google = $user->createToken('google', ['*', 'auth:google'])->plainTextToken;
        $manual = $user->createToken('manual', ['*', 'auth:password'])->plainTextToken;
        $this->withToken($google)->getJson('/api/user/account')->assertOk()
            ->assertJsonPath('account.registered_with_google', false)->assertJsonPath('account.authentication_method', 'google')->assertJsonPath('account.can_change_password', false);
        $this->withToken($google)->putJson('/api/user/password', $this->payload())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($manual)->getJson('/api/user/account')->assertOk()
            ->assertJsonPath('account.authentication_method', 'password')->assertJsonPath('account.can_change_password', true);
        $this->withToken($manual)->putJson('/api/user/password', $this->payload())->assertOk();
        $this->assertFalse($user->fresh()->registered_with_google);
    }

    public function test_google_login_issues_a_google_session_marker_without_changing_registration_source(): void
    {
        Schema::create('students', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); });
        $user = $this->account();
        $googleLogin = $this->postJson('/api/google-login', ['email' => $user->email])->assertOk();
        $this->withToken($googleLogin->json('token'))->getJson('/api/user/account')->assertOk()
            ->assertJsonPath('account.authentication_method', 'google')->assertJsonPath('account.can_change_password', false);
        $this->assertFalse($user->fresh()->registered_with_google);
        $this->app['auth']->forgetGuards();
        $manualLogin = $this->postJson('/api/login', ['email' => $user->email, 'password' => self::ORIGINAL])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($manualLogin->json('token'))->getJson('/api/user/account')->assertOk()->assertJsonPath('account.can_change_password', true);
    }

    public function test_registration_source_is_recorded_for_each_mobile_role(): void
    {
        $tables = [
            'students' => ['student_name', 'student_school_name', 'course', 'student_schedule', 'year_level', 'contact_number', 'age', 'gender', 'location', 'detailed_address', 'latitude', 'longitude', 'avatar'],
            'employers' => ['employer_name', 'hirer_name', 'contact_number', 'location', 'detailed_address', 'latitude', 'longitude', 'business_type', 'avatar'],
            'households' => ['household_name', 'cp_number', 'age', 'gender', 'location', 'detailed_address', 'latitude', 'longitude', 'avatar'],
        ];
        foreach ($tables as $table => $columns) {
            Schema::create($table, function (Blueprint $schema) use ($columns) {
                $schema->id(); $schema->unsignedBigInteger('user_id');
                foreach ($columns as $column) $schema->string($column)->nullable();
                $schema->boolean('isVerified')->default(false); $schema->boolean('isSubscribed')->default(false); $schema->timestamps();
            });
        }
        foreach (['student', 'employer', 'household'] as $role) {
            foreach ([true, false] as $google) {
                $email = $role.($google ? '-google' : '-manual').'@example.com';
                $response = $this->postJson('/api/register/'.$role, [
                    'first_name' => 'Test', 'last_name' => 'Account', 'email' => $email,
                    'password' => self::ORIGINAL, 'phone' => '09123456789',
                    'school_name' => 'Test School', 'course' => 'BSIT', 'year_level' => '4th Year',
                    'business_name' => 'Test Business', 'business_type' => 'Services', 'registered_with_google' => $google,
                ])->assertCreated()->assertJsonPath('user.registered_with_google', $google);
                $user = User::where('email', $email)->firstOrFail();
                $this->assertSame($google, $user->registered_with_google);
                $this->app['auth']->forgetGuards();
                $this->withToken($response->json('token'))->getJson('/api/user/account')->assertOk()
                    ->assertJsonPath('account.can_change_password', !$google);
            }
        }
    }

    public function test_registration_source_migration_preserves_existing_passwords_and_is_idempotent(): void
    {
        $user = $this->account();
        Schema::table('users', function (Blueprint $table) { $table->dropColumn('registered_with_google'); });
        $migration = require database_path('migrations/2026_10_12_120000_add_registered_with_google_to_users_table.php');
        $migration->up();
        $migration->up();
        $this->assertFalse($user->fresh()->registered_with_google);
        $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
    }

    public function test_all_mobile_roles_can_change_only_their_own_password(): void
    {
        $other = $this->account('other');
        foreach (['student', 'employer', 'household'] as $role) {
            $user = $this->account($role);
            $token = $user->createToken('phone')->plainTextToken;
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->putJson('/api/user/password', $this->payload(['user_id' => $other->id, 'role' => 'admin']))
                ->assertOk()->assertJsonPath('status', 'success')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertTrue(Hash::check(self::UPDATED, $user->fresh()->password));
            $this->assertFalse(Hash::check(self::ORIGINAL, $user->fresh()->password));
            $this->assertTrue(Hash::check(self::ORIGINAL, $other->fresh()->password));
        }
    }

    public function test_incorrect_current_password_does_not_change_password_or_sessions(): void
    {
        $user = $this->account();
        $token = $user->createToken('phone')->plainTextToken;
        $user->createToken('other device');
        $response = $this->withToken($token)->putJson('/api/user/password', $this->payload(['current_password' => 'Wrong-password1!']));
        $response->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
        $this->assertSame(2, $user->tokens()->count());
        $response->assertDontSee(self::UPDATED, false)->assertDontSee('Wrong-password1!', false);
    }

    public function test_password_requirements_confirmation_and_reuse_are_enforced(): void
    {
        $user = $this->account();
        $token = $user->createToken('phone')->plainTextToken;
        foreach (['Short1!', 'lowercase1!', 'Uppercase!', 'Uppercase12', self::ORIGINAL, str_repeat('A', 70).'1!x', str_repeat('é', 30).'Uppercase1!extra', "New-password2!\x00"] as $password) {
            $this->withToken($token)->putJson('/api/user/password', $this->payload(['password' => $password, 'password_confirmation' => $password]))
                ->assertUnprocessable()->assertJsonValidationErrors('password');
            // Isolate validation cases from the separate rate-limit check below.
            $this->app['cache']->store()->flush();
            $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
        }
        $this->withToken($token)->putJson('/api/user/password', $this->payload(['password_confirmation' => 'Different-password3!']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_other_tokens_are_revoked_while_the_current_device_remains_authenticated(): void
    {
        $user = $this->account();
        $current = $user->createToken('current')->plainTextToken;
        $other = $user->createToken('other')->plainTextToken;
        $this->withToken($current)->putJson('/api/user/password', $this->payload())->assertOk();
        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_password_spaces_and_punctuation_are_preserved_exactly(): void
    {
        $user = $this->account();
        $original = ' Original-password1! ';
        $updated = ' <Updated-password2!> ';
        $user->forceFill(['password' => Hash::make($original)])->save();
        $token = $user->createToken('phone')->plainTextToken;
        $this->withToken($token)->putJson('/api/user/password', $this->payload([
            'current_password' => $original, 'password' => $updated, 'password_confirmation' => $updated,
        ]))->assertOk();
        $this->assertTrue(Hash::check($updated, $user->fresh()->password));
        $this->assertFalse(Hash::check(trim($updated), $user->fresh()->password));
    }

    public function test_repeated_incorrect_password_attempts_are_rate_limited(): void
    {
        $user = $this->account();
        $token = $user->createToken('phone')->plainTextToken;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withToken($token)->putJson('/api/user/password', $this->payload(['current_password' => 'Wrong-password1!']))->assertUnprocessable();
        }
        $this->withToken($token)->putJson('/api/user/password', $this->payload())->assertStatus(429);
        $this->assertTrue(Hash::check(self::ORIGINAL, $user->fresh()->password));
    }
}
