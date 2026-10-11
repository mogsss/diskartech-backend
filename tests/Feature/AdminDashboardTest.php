<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleAdminSession::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Carbon::setTestNow(Carbon::parse('2026-10-11 05:00:00', 'UTC'));
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('email'); $table->string('role'); $table->timestamps(); });
        foreach (['students', 'employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id(); $table->unsignedBigInteger('user_id'); $table->boolean('isVerified')->default(false);
                $table->string('rejection_reason')->nullable(); $table->timestamps();
                foreach (match ($name) {
                    'students' => ['student_name', 'student_school_name', 'school_id', 'coe', 'student_resume', 'school_id_ai_remarks', 'coe_ai_remarks'],
                    'employers' => ['employer_name', 'hirer_name', 'valid_id_path', 'employer_certificate_path', 'ai_remarks', 'cert_ai_remarks'],
                    'households' => ['household_name', 'valid_id_path', 'ai_remarks'],
                } as $field) $table->string($field)->nullable();
            });
        }
        Schema::create('available_jobs', function (Blueprint $table) { $table->id(); $table->string('title'); $table->string('status'); $table->timestamps(); });
        Schema::create('reports', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('reporter_id'); $table->unsignedBigInteger('reported_user_id')->nullable();
            $table->unsignedBigInteger('job_id')->nullable(); $table->string('subject'); $table->text('description');
            $table->string('report_type'); $table->string('status'); $table->timestamps();
        });
        $admin = new Admin; $admin->id = 1; $admin->name = 'Test Administrator';
        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(string $role, array $profile = [], string $created = '2026-10-11 01:00:00'): int
    {
        $id = DB::table('users')->insertGetId(['email' => 'account-'.uniqid().'@example.com', 'role' => $role, 'created_at' => $created, 'updated_at' => $created]);
        $data = match ($role) {
            'student' => ['student_name' => 'Student '.$id, 'student_school_name' => 'Example School', 'school_id' => 'school.pdf', 'coe' => 'coe.pdf', 'student_resume' => 'resume.pdf'],
            'employer' => ['employer_name' => 'Example Business', 'hirer_name' => 'Hirer '.$id, 'valid_id_path' => 'valid-id.pdf', 'employer_certificate_path' => 'permit.pdf'],
            'household' => ['household_name' => 'Household '.$id, 'valid_id_path' => 'valid-id.pdf'],
            default => null,
        };
        if ($data !== null) DB::table($role.'s')->insert(array_replace(['user_id' => $id, 'created_at' => $created, 'updated_at' => $created], $data, $profile));
        return $id;
    }

    public function test_overview_counts_and_queue_show_real_actionable_records(): void
    {
        $student = $this->account('student', ['student_name' => "Ana O'Neil <script>alert(1)</script>"]);
        $this->account('student', ['isVerified' => true]);
        $this->account('student', ['student_resume' => null]);
        $employer = $this->account('employer');
        $this->account('employer', ['isVerified' => true]);
        $this->account('employer', ['rejection_reason' => 'Invalid document']);
        $household = $this->account('household');
        $this->account('household', ['valid_id_path' => '']);
        $this->account('admin');
        foreach (['active', 'active', 'hidden', 'closed'] as $status) DB::table('available_jobs')->insert(['title' => 'Example job', 'status' => $status]);
        foreach (['pending', 'investigating', 'resolved', 'pending', 'pending'] as $index => $status) DB::table('reports')->insert([
            'reporter_id' => $student, 'subject' => 'Example incident '.$index, 'description' => 'Example report details.', 'report_type' => 'other',
            'status' => $status, 'created_at' => '2026-10-11 02:00:00', 'updated_at' => '2026-10-11 02:00:00',
        ]);
        $response = $this->get('/admin/dashboard')->assertOk()->assertViewHas('studentsCount', 3)
            ->assertViewHas('employerCount', 3)->assertViewHas('householdCount', 2)->assertViewHas('openJobsCount', 2)
            ->assertViewHas('pendingVerificationCount', 3)->assertViewHas('flaggedCount', 4);
        $this->assertSame([$household, $employer, $student], $response->viewData('users')->pluck('id')->all());
        $this->assertSame([5, 4, 2], $response->viewData('flaggedReports')->pluck('id')->all());
        $response->assertSee('General incident')->assertSee('showReview(')->assertSee('schoolIdUrl')
            ->assertDontSee('Real-time database sync active.')->assertDontSee('<script>alert(1)</script>', false);
        // Optional fixture-only browser preview; never reads production data.
        if ($path = getenv('DISKARTECH_DASHBOARD_PREVIEW_PATH')) file_put_contents($path, $response->getContent());
    }

    public function test_queue_preview_is_limited_to_five_with_a_full_count(): void
    {
        $ids = [];
        for ($i = 0; $i < 8; $i++) $ids[] = $this->account('household');
        $response = $this->get('/admin/dashboard')->assertOk()->assertViewHas('pendingVerificationCount', 8);
        $this->assertSame(array_slice(array_reverse($ids), 0, 5), $response->viewData('users')->pluck('id')->all());
    }

    public function test_weekly_activity_uses_seven_real_philippine_calendar_days(): void
    {
        $this->account('student', [], '2026-10-04 15:59:59'); // Oct 4 in Philippine time, outside period.
        $this->account('student', [], '2026-10-04 16:00:00'); // Oct 5 midnight, included.
        $this->account('employer', [], '2026-10-10 16:00:00'); // Oct 11 midnight.
        $this->account('household', [], '2026-10-11 04:00:00');
        $this->account('admin', [], '2026-10-11 04:00:00');
        $this->account('student', [], '2026-10-12 00:00:00'); // Future record is not activity.
        $response = $this->get('/admin/dashboard')->assertOk();
        $activity = $response->viewData('weeklyActivity');
        $this->assertSame([1, 0, 0, 0, 0, 0, 2], $activity->pluck('count')->all());
        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], $activity->pluck('label')->all());
    }

    public function test_empty_dashboard_renders_without_sample_metrics(): void
    {
        $response = $this->get('/admin/dashboard')->assertOk()->assertViewHas('pendingVerificationCount', 0)
            ->assertViewHas('openJobsCount', 0)->assertSee('No accounts waiting for review')
            ->assertSee('No open incidents')->assertSee('No new registrations during this period.');
        $this->assertSame(0, $response->viewData('weeklyActivity')->sum('count'));
    }

    public function test_dashboard_requires_admin_authentication(): void
    {
        auth('admin')->logout();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }
}
