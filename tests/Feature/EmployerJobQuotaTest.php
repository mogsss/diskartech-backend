<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Jobs;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployerJobQuotaTest extends TestCase
{
    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        Http::preventStrayRequests();
        DB::connection()->setQueryGrammar(new class(DB::connection()) extends SQLiteGrammar {
            protected function compileHavings(Builder $query) {
                $sql = parent::compileHavings($query);
                return $query->from === 'students' && empty($query->groups) ? preg_replace('/^having /', 'and ', $sql) : $sql;
            }
        });
        foreach (['radians' => 'deg2rad', 'cos' => 'cos', 'sin' => 'sin', 'acos' => 'acos'] as $sql => $php) DB::connection()->getPdo()->sqliteCreateFunction($sql, $php, 1);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        foreach (['employers', 'households'] as $name) Schema::create($name, function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id')->unique(); $table->boolean('isVerified')->default(true);
            $table->double('latitude')->default(13.04); $table->double('longitude')->default(121.49);
            $table->boolean('isSubscribed')->default(false); $table->timestamp('subscription_expires_at')->nullable();
        });
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('title'); $table->text('description');
            $table->decimal('salary', 10, 2); $table->string('salary_type')->default('day'); $table->string('category');
            $table->text('available_days'); $table->string('time_slot'); $table->text('skills')->nullable(); $table->text('requirements')->nullable();
            $table->double('latitude')->nullable(); $table->double('longitude')->nullable(); $table->string('status')->nullable()->default('active'); $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); $table->string('student_name'); $table->double('latitude'); $table->double('longitude'); });
        Schema::create('job_applications', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('job_id'); $table->unsignedBigInteger('student_id'); });
        $this->employer = $this->account('employer', 'employer@example.com');
        Sanctum::actingAs($this->employer);
    }

    private function account(string $role, string $email): User
    {
        $user = User::create(['role' => $role, 'email' => $email, 'password' => 'fixture']);
        if ($role !== 'student') DB::table($role.'s')->insert(['user_id' => $user->id]);
        return $user;
    }

    private function payload(): array
    {
        return ['title' => 'Student assistant', 'description' => 'Help with work.', 'salary' => 100, 'salary_type' => 'day', 'category' => 'General', 'available_days' => ['Mon'], 'time_slot' => 'Morning'];
    }

    private function seedJob(?string $status = 'active', ?User $owner = null): Jobs
    {
        return Jobs::create([...$this->payload(), 'user_id' => ($owner ?? $this->employer)->id, 'status' => $status]);
    }

    public function test_basic_allows_three_posts_and_rejects_the_fourth_without_inserting_a_row(): void
    {
        $this->getJson('/api/employer/job-quota')->assertOk()->assertJsonPath('job_quota.active_job_limit', 3)->assertJsonPath('job_quota.remaining_job_slots', 3);
        for ($i = 0; $i < 3; $i++) $this->postJson('/api/jobs', [...$this->payload(), 'isSubscribed' => true, 'subscription_plan' => 'premium', 'active_job_limit' => 100])->assertCreated();
        $this->postJson('/api/jobs', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('active_job_limit');
        $this->assertDatabaseCount('available_jobs', 3);
        $this->getJson('/api/employer/jobs')->assertOk()->assertJsonPath('job_quota.active_job_count', 3)->assertJsonPath('job_quota.can_post_job', false);
    }

    public function test_active_premium_has_no_cap_and_pending_or_expired_flags_do_not_unlock_it(): void
    {
        DB::table('employers')->update(['isSubscribed' => true, 'subscription_expires_at' => now()->addMonth()]);
        for ($i = 0; $i < 12; $i++) $this->postJson('/api/jobs', $this->payload())->assertCreated();
        $closed = $this->seedJob('closed');
        $this->patchJson('/api/employer/jobs/'.$closed->id.'/status', ['status' => 'active'])->assertOk();
        $this->getJson('/api/employer/job-quota')->assertOk()->assertJsonPath('job_quota.plan', 'premium')->assertJsonPath('job_quota.active_job_limit', null)
            ->assertJsonPath('job_quota.remaining_job_slots', null)->assertJsonPath('job_quota.can_post_job', true)->assertJsonPath('job_quota.active_job_count', 13);
        DB::table('employers')->update(['isSubscribed' => false, 'subscription_expires_at' => now()->addMonth()]);
        $this->getJson('/api/employer/job-quota')->assertOk()->assertJsonPath('job_quota.plan', 'basic');
        DB::table('employers')->update(['isSubscribed' => true, 'subscription_expires_at' => now()->subSecond()]);
        $this->getJson('/api/employer/job-quota')->assertOk()->assertJsonPath('job_quota.active_job_limit', 3)->assertJsonPath('job_quota.remaining_job_slots', 0);
        $this->postJson('/api/jobs', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('active_job_limit');
        $this->assertDatabaseCount('available_jobs', 13);
    }

    public function test_closing_or_deleting_frees_a_slot_but_reopening_is_checked(): void
    {
        $first = $this->seedJob(); $second = $this->seedJob(); $third = $this->seedJob(); $closed = $this->seedJob('closed');
        $this->patchJson('/api/employer/jobs/'.$closed->id.'/status', ['status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('active_job_limit');
        $this->patchJson('/api/employer/jobs/'.$first->id.'/status', ['status' => 'closed'])->assertOk();
        $this->patchJson('/api/employer/jobs/'.$closed->id.'/status', ['status' => 'active'])->assertOk();
        $this->patchJson('/api/employer/jobs/'.$first->id.'/status', ['status' => 'active'])->assertUnprocessable();
        $this->deleteJson('/api/jobs/'.$second->id)->assertOk();
        $this->postJson('/api/jobs', $this->payload())->assertCreated();
        $this->patchJson('/api/employer/jobs/'.$third->id.'/status', ['status' => 'active'])->assertOk();
        $this->getJson('/api/employer/job-quota')->assertOk()->assertJsonPath('job_quota.active_job_count', 3);
    }

    public function test_expired_premium_keeps_existing_jobs_editable_but_cannot_increase_active_count(): void
    {
        DB::table('employers')->update(['isSubscribed' => true, 'subscription_expires_at' => now()->subDay()]);
        for ($i = 0; $i < 10; $i++) $this->seedJob();
        $closed = $this->seedJob('closed');
        $this->postJson('/api/jobs', $this->payload())->assertUnprocessable();
        $this->patchJson('/api/employer/jobs/'.$closed->id.'/status', ['status' => 'active'])->assertUnprocessable();
        $jobs = Jobs::where('status', 'active')->get();
        $this->putJson('/api/jobs/'.$jobs[0]->id, [...$this->payload(), 'title' => 'Updated assistant', 'status' => 'closed'])->assertOk()->assertJsonPath('job.status', 'active');
        foreach ($jobs->take(7) as $job) $this->patchJson('/api/employer/jobs/'.$job->id.'/status', ['status' => 'closed'])->assertOk();
        $this->postJson('/api/jobs', $this->payload())->assertUnprocessable();
        $this->patchJson('/api/employer/jobs/'.$jobs[7]->id.'/status', ['status' => 'closed'])->assertOk();
        $this->postJson('/api/jobs', $this->payload())->assertCreated();
    }

    public function test_legacy_and_hidden_statuses_reserve_slots_and_counts_are_owner_specific(): void
    {
        foreach ([null, '', 'hidden', ' CLOSED ', 'inactive', 'archived', '0', 'FALSE'] as $status) $this->seedJob($status);
        $other = $this->account('employer', 'other@example.com');
        $otherJob = $this->seedJob('closed', $other); $this->seedJob('active', $other);
        $this->getJson('/api/employer/job-quota')->assertOk()->assertJsonPath('job_quota.active_job_count', 3);
        $this->patchJson('/api/employer/jobs/'.$otherJob->id.'/status', ['status' => 'active'])->assertNotFound();
        $this->postJson('/api/jobs', $this->payload())->assertUnprocessable();
    }

    public function test_household_posting_is_unchanged_and_verification_and_authentication_still_apply(): void
    {
        $household = $this->account('household', 'household@example.com'); Sanctum::actingAs($household);
        for ($i = 0; $i < 11; $i++) $this->postJson('/api/jobs', $this->payload())->assertCreated();
        $this->getJson('/api/employer/job-quota')->assertOk()->assertJsonPath('job_quota', null);
        Sanctum::actingAs($this->employer);
        DB::table('employers')->update(['isVerified' => false]);
        $this->postJson('/api/jobs', $this->payload())->assertForbidden()->assertJsonPath('needs_verification', true);
        Sanctum::actingAs($this->account('student', 'student@example.com'));
        $this->postJson('/api/jobs', $this->payload())->assertForbidden();
        $this->getJson('/api/employer/job-quota')->assertForbidden();
    }

    public function test_admin_reopening_obeys_the_same_limit_and_hidden_restoration_keeps_its_slot(): void
    {
        $hidden = $this->seedJob('hidden'); $this->seedJob(); $this->seedJob(); $closed = $this->seedJob('closed');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleAdminSession::class);
        $admin = new Admin; $admin->id = 1; $admin->name = 'Test admin'; $this->actingAs($admin, 'admin');
        $this->patchJson('/admin/job-moderation/'.$closed->id.'/status', ['status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('active_job_limit');
        $this->post('/admin/job-moderation/'.$hidden->id.'/toggle')->assertRedirect();
        $this->assertDatabaseHas('available_jobs', ['id' => $hidden->id, 'status' => 'active']);
        $this->patch('/admin/job-moderation/'.$hidden->id.'/status', ['status' => 'closed'])->assertRedirect();
        $this->patch('/admin/job-moderation/'.$closed->id.'/status', ['status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('available_jobs', ['id' => $closed->id, 'status' => 'active']);
    }
}
