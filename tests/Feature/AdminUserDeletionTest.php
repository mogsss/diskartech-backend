<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Services\AccountDeletion;
use App\Support\AdminSession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserDeletionTest extends TestCase
{
    private Admin $admin;
    private const PASSWORD = 'Admin-password1!';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'cache.limiter' => 'array', 'session.driver' => 'array']);
        Http::preventStrayRequests();
        foreach (['0001_01_01_000000_create_users_table.php', '2026_08_14_092718_create_admins_table.php',
            '2026_08_14_025124_create_personal_access_tokens_table.php', '2026_10_11_180000_add_active_session_to_admins_table.php',
            '2026_10_12_140000_create_mobile_password_resets_table.php', '2026_10_16_120000_create_account_deletion_tables.php',
            '2026_10_20_120000_create_admin_user_deletions_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('registered_with_google')->default(false);
            $table->boolean('isEmailVerified')->default(true);
            $table->string('otp_code')->nullable(); $table->timestamp('otp_expires_at')->nullable();
        });
        foreach (['students', 'employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->foreignId('user_id')->constrained('users');
                foreach (['student_name', 'student_school_name', 'employer_name', 'hirer_name', 'household_name',
                    'business_type', 'course', 'year_level', 'contact_number', 'cp_number', 'location', 'detailed_address', 'avatar'] as $field) {
                    $table->string($field)->nullable();
                }
                $table->boolean('isVerified')->default(true); $table->timestamps();
            });
        }
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained('users');
            $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id(); $table->foreignId('job_id')->constrained('available_jobs');
            $table->foreignId('student_id')->constrained('students'); $table->string('status');
            $table->timestamp('terminated_at')->nullable(); $table->text('termination_reason')->nullable();
            $table->timestamp('interview_ended_at')->nullable(); $table->timestamps();
        });
        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('student_id'); $table->unsignedBigInteger('job_id');
        });
        $this->admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make(self::PASSWORD)]);
        // Keep real admin authentication and single-session ownership middleware enabled.
        $this->actingAs($this->admin, 'admin');
    }

    private function account(string $role = 'student', bool $profile = true): User
    {
        $user = User::create(['email' => uniqid().'@example.com', 'password' => 'User-password1!', 'role' => $role]);
        if ($profile) DB::table($role.'s')->insert([
            'user_id' => $user->id, $role.'_name' => 'Private Name', 'contact_number' => '09123456789',
            'avatar' => 'student/profile_pictures/private-avatar.jpg',
        ]);
        $user->createToken('phone');
        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['reason' => 'Duplicate account confirmed by support.', 'confirmation' => 'DELETE',
            'acknowledged' => true, 'current_password' => self::PASSWORD], $overrides);
    }

    public function test_guests_and_mobile_users_cannot_delete_from_the_admin_route(): void
    {
        $user = $this->account();
        auth('admin')->logout();
        $this->delete('/admin/users/'.$user->id, $this->payload())->assertRedirect(route('login'));
        $this->deleteJson('/admin/users/'.$user->id, $this->payload())->assertUnauthorized();
        $this->actingAs($user, 'web')->deleteJson('/admin/users/'.$user->id, $this->payload())->assertUnauthorized();
        $this->assertNotNull(User::find($user->id));
        $this->assertDatabaseCount('admin_user_deletions', 0);
    }

    public function test_expired_admin_sessions_cannot_delete_even_with_a_correct_password(): void
    {
        $user = $this->account();
        $token = str_repeat('a', 64);
        $this->admin->forceFill(['active_session_token' => hash('sha256', $token), 'active_session_expires_at' => now()->subMinute()->timestamp])->save();
        $this->withSession([AdminSession::SESSION_KEY => $token])
            ->deleteJson('/admin/users/'.$user->id, $this->payload())->assertUnauthorized();
        $this->assertNotNull(User::find($user->id));
        $this->assertDatabaseCount('admin_user_deletions', 0);
    }

    public function test_all_mobile_roles_can_be_deleted_and_actor_and_original_role_are_audited(): void
    {
        $other = $this->account();
        foreach (['student', 'employer', 'household'] as $role) {
            $user = $this->account($role);
            $user->createToken('second phone');
            $response = $this->deleteJson('/admin/users/'.$user->id, $this->payload([
                'admin_id' => 999, 'user_id' => $other->id, 'reason' => "  Confirmed duplicate account.\x00  ",
            ]))->assertOk()->assertSessionHas('success');
            $this->assertNull(User::find($user->id));
            $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'deleted']);
            $this->assertDatabaseHas('admin_user_deletions', ['admin_id' => $this->admin->id,
                'user_id' => $user->id, 'user_role' => $role, 'reason' => 'Confirmed duplicate account.']);
            $this->assertDatabaseHas($role.'s', ['user_id' => $user->id, $role.'_name' => 'Deleted account',
                'contact_number' => null, 'avatar' => null, 'isVerified' => false]);
            $this->assertDatabaseHas('account_deletion_files', ['user_id' => $user->id, 'path' => 'student/profile_pictures/private-avatar.jpg']);
            $this->assertSame(0, $user->tokens()->count());
            $this->assertNotNull(User::find($other->id));
            $this->assertNotNull($this->admin->fresh());
            $response->assertDontSee(self::PASSWORD)->assertDontSee('Private Name');
        }
        $this->assertDatabaseCount('admin_user_deletions', 3);
        $this->get('/admin/users')->assertOk()->assertViewHas('totalAccounts', 1);
    }

    public function test_confirmation_reason_and_admin_password_are_required_without_flashing_credentials(): void
    {
        $user = $this->account();
        foreach (['confirmation' => 'delete', 'acknowledged' => false, 'reason' => ' ', 'current_password' => 'Wrong-password1!'] as $field => $value) {
            $this->deleteJson('/admin/users/'.$user->id, $this->payload([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->from('/admin/users')->delete('/admin/users/'.$user->id, $this->payload(['current_password' => 'Wrong-password1!']))
            ->assertRedirect('/admin/users')->assertSessionHasErrors('current_password');
        $this->assertNull(session('_old_input.current_password'));
        $this->assertNotNull(User::find($user->id));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseCount('admin_user_deletions', 0);
    }

    public function test_markup_and_overlong_reasons_are_rejected(): void
    {
        $user = $this->account();
        foreach (['<script>bad</script>', '&lt;b&gt;Reason&lt;/b&gt;', str_repeat('a', 1001)] as $reason) {
            $this->deleteJson('/admin/users/'.$user->id, $this->payload(['reason' => $reason]))
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->assertNotNull(User::find($user->id));
        $this->assertDatabaseCount('admin_user_deletions', 0);
    }

    public function test_unknown_non_mobile_and_repeated_targets_cannot_be_deleted(): void
    {
        $nonMobile = $this->account('admin', false);
        $user = $this->account('student', false);
        foreach ([99999, $nonMobile->id] as $id) {
            $this->deleteJson('/admin/users/'.$id, $this->payload())->assertNotFound();
        }
        $this->deleteJson('/admin/users/'.$user->id, $this->payload())->assertOk();
        $this->deleteJson('/admin/users/'.$user->id, $this->payload())->assertNotFound();
        $this->assertDatabaseCount('admin_user_deletions', 1);
        $this->assertNotNull(User::find($nonMobile->id));
    }

    public function test_admin_cleanup_preserves_shared_work_and_clears_reset_and_deletion_challenges(): void
    {
        $hirer = $this->account('employer'); $student = $this->account();
        $studentId = DB::table('students')->where('user_id', $student->id)->value('id');
        $jobId = DB::table('available_jobs')->insertGetId(['user_id' => $hirer->id]);
        foreach (['hired', 'pending', 'completed'] as $status) {
            DB::table('job_applications')->insert(['job_id' => $jobId, 'student_id' => $studentId, 'status' => $status]);
        }
        DB::table('account_deletion_codes')->insert(['user_id' => $hirer->id, 'session_hash' => 'secret', 'code_hash' => 'secret',
            'expires_at' => now()->addMinutes(5), 'sent_at' => now()]);
        DB::table('mobile_password_resets')->insert(['user_id' => $hirer->id, 'email' => $hirer->email,
            'expires_at' => now()->addMinutes(5), 'sent_at' => now()]);
        $this->delete('/admin/users/'.$hirer->id, $this->payload())->assertRedirect(route('admin.users'))->assertSessionHas('success');
        $this->assertDatabaseHas('available_jobs', ['id' => $jobId, 'status' => 'closed']);
        $this->assertSame(['terminated', 'cancelled', 'completed'], DB::table('job_applications')->orderBy('id')->pluck('status')->all());
        $reason = DB::table('job_applications')->where('status', 'terminated')->value('termination_reason');
        $this->assertStringContainsString('administrator', $reason);
        $this->assertStringNotContainsString($this->payload()['reason'], $reason);
        $this->assertDatabaseMissing('account_deletion_codes', ['user_id' => $hirer->id]);
        $this->assertDatabaseMissing('mobile_password_resets', ['user_id' => $hirer->id]);
        $this->assertNotNull(User::find($student->id));
    }

    public function test_a_failed_cleanup_rolls_back_the_audit_and_account_changes(): void
    {
        $user = $this->account();
        $this->mock(AccountDeletion::class)->shouldReceive('delete')->once()->withArgs(fn ($target, $byAdmin) => $target->id === $user->id && $byAdmin)
            ->andReturnUsing(function ($target) {
                DB::table('students')->where('user_id', $target->id)->update(['contact_number' => null]);
                $target->tokens()->delete();
                throw new \RuntimeException('Test cleanup failure');
            });
        $this->deleteJson('/admin/users/'.$user->id, $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('admin_user_deletions', 0);
        $this->assertDatabaseHas('students', ['user_id' => $user->id, 'contact_number' => '09123456789']);
        $this->assertNotNull(User::find($user->id));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_an_audit_failure_prevents_cleanup_and_the_migration_can_be_reapplied(): void
    {
        $migration = require database_path('migrations/2026_10_20_120000_create_admin_user_deletions_table.php');
        $migration->up();
        $user = $this->account();
        DB::table('admin_user_deletions')->insert(['admin_id' => $this->admin->id, 'user_id' => $user->id,
            'user_role' => $user->role, 'reason' => 'Existing audit fixture', 'created_at' => now()]);
        $this->mock(AccountDeletion::class)->shouldNotReceive('delete');
        $this->deleteJson('/admin/users/'.$user->id, $this->payload())->assertStatus(500);
        $this->assertNotNull(User::find($user->id));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseCount('admin_user_deletions', 1);
    }

    public function test_password_attempts_on_the_delete_route_are_throttled(): void
    {
        $user = $this->account();
        for ($i = 0; $i < 5; $i++) {
            $this->deleteJson('/admin/users/'.$user->id, $this->payload(['current_password' => 'Wrong-password1!']))->assertUnprocessable();
        }
        $this->deleteJson('/admin/users/'.$user->id, $this->payload())->assertStatus(429);
        $this->assertNotNull(User::find($user->id));
    }
}
