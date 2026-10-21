<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\Jobs;
use App\Models\Student;
use App\Models\User;
use App\Services\WeeklySchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeeklyScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'services.gemini.key' => null, 'services.openai.matching_enabled' => false]);
        Http::preventStrayRequests();
        // Match the existing MySQL nearby-student HAVING query on SQLite fixtures.
        DB::connection()->setQueryGrammar(new class(DB::connection()) extends \Illuminate\Database\Query\Grammars\SQLiteGrammar {
            protected function compileHavings(\Illuminate\Database\Query\Builder $query) {
                $sql = parent::compileHavings($query);
                return $query->from === 'students' && empty($query->groups) ? preg_replace('/^having /', 'and ', $sql) : $sql;
            }
        });
        $pdo = DB::connection()->getPdo();
        foreach (['radians' => 'deg2rad', 'sin' => 'sin', 'cos' => 'cos', 'acos' => 'acos'] as $sql => $php) $pdo->sqliteCreateFunction($sql, $php, 1);
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('role'); });
        Schema::create('students', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('student_name')->default('Student'); $t->text('available_days')->nullable();
            $t->string('time_slot')->nullable(); $t->text('skillset')->nullable(); $t->boolean('isVerified')->default(true);
            $t->double('latitude')->nullable(); $t->double('longitude')->nullable(); $t->timestamps();
        });
        foreach (['employers', 'households'] as $table) Schema::create($table, function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->boolean('isVerified')->default(true);
            $t->boolean('isSubscribed')->default(false); $t->timestamp('subscription_expires_at')->nullable();
            $t->double('latitude')->default(12.5); $t->double('longitude')->default(121.5);
        });
        Schema::create('available_jobs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id');
            foreach (['title', 'description', 'category', 'available_days', 'time_slot', 'requirements', 'skills', 'status'] as $name) $t->text($name)->nullable();
            $t->decimal('salary')->default(450); $t->string('salary_type')->default('day');
            $t->double('latitude')->default(12.5); $t->double('longitude')->default(121.5); $t->timestamps();
        });
        Schema::create('job_applications', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('job_id'); $t->unsignedBigInteger('student_id'); $t->string('status')->default('viewed');
            foreach (['start_date', 'end_date', 'work_schedule', 'agreed_rate', 'special_instructions', 'contract_terms'] as $name) $t->text($name)->nullable();
            $t->timestamp('hired_at')->nullable(); $t->timestamps();
        });
        (require database_path('migrations/2026_10_20_180000_create_weekly_schedule_slots.php'))->up();
        DB::table('users')->insert([['id' => 1, 'role' => 'student'], ['id' => 2, 'role' => 'employer'], ['id' => 3, 'role' => 'household']]);
        DB::table('students')->insert(['id' => 1, 'user_id' => 1, 'available_days' => '["Mon"]', 'time_slot' => 'Whole Day', 'latitude' => 12.5, 'longitude' => 121.5]);
        DB::table('employers')->insert(['user_id' => 2]); DB::table('households')->insert(['user_id' => 3]);
        $this->login(1);
    }

    private function login(int $id): void { Sanctum::actingAs(User::findOrFail($id)); }
    private function slot(string $day = 'Mon', string $start = '09:30', string $end = '15:00', bool $next = false): array
    { return ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end, 'ends_next_day' => $next]; }
    private function job(array $slots, int $owner = 2): Jobs
    {
        $job = Jobs::create(['user_id' => $owner, 'title' => 'Assistant', 'description' => 'Help customers', 'category' => 'General', 'status' => 'active', 'available_days' => WeeklySchedule::days($slots), 'time_slot' => WeeklySchedule::summary($slots), 'skills' => ['Service']]);
        $job->replaceWeeklySlots($slots);
        return $job;
    }
    private function availability(array $slots): void { $this->login(1); $this->postJson('/api/student/update-availability', ['schedule_slots' => $slots])->assertOk(); }

    public function test_student_can_save_reload_and_replace_independent_days_and_split_hours(): void
    {
        $slots = [$this->slot('Mon', '09:30', '12:00'), $this->slot('Mon', '13:00', '15:00'), $this->slot('Wed', '22:00', '06:00', true)];
        $this->postJson('/api/student/update-availability', ['schedule_slots' => $slots])->assertOk()->assertJsonPath('profile.schedule_slots', $slots);
        $this->getJson('/api/student/profile')->assertOk()->assertJsonPath('profile.schedule_slots', $slots)->assertJsonPath('profile.available_days', ['Mon', 'Wed']);
        $this->availability([$this->slot('Sat', '00:00', '00:00', true)]);
        $this->assertDatabaseCount('student_availability_slots', 1);
    }

    public function test_invalid_and_cross_midnight_overlaps_are_rejected_without_deleting_saved_hours(): void
    {
        $this->availability([$this->slot()]);
        foreach ([[], [$this->slot('Mon', '15:00', '09:00')], [$this->slot('Mon', '09:00', '09:00')], [$this->slot('Mon', '9:00', '15:00')],
            [$this->slot('Mon', '06:00', '07:00', true)], [$this->slot('Sun', '22:00', '06:00', true), $this->slot('Mon', '05:00', '08:00')],
            [$this->slot(), $this->slot()], [$this->slot('Nope')],
            [$this->slot('Mon', '01:00', '02:00'), $this->slot('Mon', '03:00', '04:00'), $this->slot('Mon', '05:00', '06:00'), $this->slot('Mon', '07:00', '08:00')],
        ] as $slots) $this->postJson('/api/student/update-availability', ['schedule_slots' => $slots])->assertUnprocessable();
        $this->assertDatabaseCount('student_availability_slots', 1);
    }

    public function test_weekly_writes_are_scoped_to_the_authenticated_student_and_old_clients_cannot_erase_them(): void
    {
        $this->availability([$this->slot()]);
        $this->postJson('/api/student/update-availability', ['days' => ['Tue'], 'time_slot' => 'Whole Day'])->assertUnprocessable();
        $this->login(2);
        $this->postJson('/api/student/update-availability', ['schedule_slots' => [$this->slot()], 'user_id' => 1])->assertForbidden();
        $this->assertDatabaseCount('student_availability_slots', 1);
    }

    public function test_both_hirer_roles_create_and_edit_weekly_hours_without_changing_other_jobs(): void
    {
        foreach ([2, 3] as $id) {
            $this->login($id);
            $payload = ['title' => 'Assistant', 'description' => 'Help with work', 'salary' => 450, 'category' => 'General', 'schedule_slots' => [$this->slot()], 'requirements' => ['ID'], 'skills' => ['Service']];
            $response = $this->postJson('/api/jobs', $payload)->assertCreated()->assertJsonPath('job.schedule_slots', [$this->slot()]);
            $jobId = $response->json('job.id');
            $payload['schedule_slots'] = [$this->slot('Fri', '22:00', '06:00', true)];
            $this->putJson('/api/jobs/'.$jobId, $payload)->assertOk()->assertJsonPath('job.available_days', ['Fri'])->assertJsonPath('job.schedule_slots', $payload['schedule_slots']);
            $this->login($id === 2 ? 3 : 2);
            $this->putJson('/api/jobs/'.$jobId, $payload)->assertNotFound();
        }
        $this->assertDatabaseCount('job_schedule_slots', 2);
    }

    public function test_legacy_schedules_are_read_as_ranges_without_a_destructive_backfill(): void
    {
        $student = Student::find(1);
        $this->assertSame([$this->slot('Mon', '00:00', '00:00', true)], $student->schedule_slots);
        $this->assertDatabaseCount('student_availability_slots', 0);
    }

    public function test_matching_uses_hours_in_fallback_and_refreshes_after_job_schedule_changes(): void
    {
        $this->availability([$this->slot('Mon', '09:00', '12:00'), $this->slot('Mon', '13:00', '15:00')]);
        $job = $this->job([$this->slot('Mon', '10:00', '14:00')]);
        $this->getJson('/api/student/ai-matched-jobs')->assertOk()->assertJsonPath('matched_jobs.0.schedule_match.status', 'partial')->assertJsonPath('matched_jobs.0.schedule_match.percentage', 75);
        $job->replaceWeeklySlots([$this->slot('Mon', '10:00', '12:00')]);
        $this->getJson('/api/student/ai-matched-jobs')->assertOk()->assertJsonPath('matched_jobs.0.schedule_match.status', 'full')->assertJsonPath('matched_jobs.0.schedule_match.percentage', 100);
    }

    public function test_hiring_rechecks_hours_and_active_contracts_and_freezes_the_agreed_schedule(): void
    {
        $this->availability([$this->slot('Mon', '08:00', '17:00')]);
        $job = $this->job([$this->slot('Mon', '09:00', '12:00')]);
        $first = JobApplication::create(['job_id' => $job->id, 'student_id' => 1, 'status' => 'viewed']);
        $this->login(2);
        $this->putJson('/api/employer/applications/'.$first->id.'/status', ['status' => 'accepted'])->assertOk()->assertJsonPath('application.work_schedule_slots', [$this->slot('Mon', '09:00', '12:00')]);
        $job->replaceWeeklySlots([$this->slot('Tue', '18:00', '22:00')]);
        $second = JobApplication::create(['job_id' => $this->job([$this->slot('Mon', '11:00', '13:00')])->id, 'student_id' => 1, 'status' => 'viewed']);
        $this->putJson('/api/employer/applications/'.$second->id.'/status', ['status' => 'accepted'])->assertUnprocessable()
            ->assertJsonPath('errors.work_schedule.0', 'An active contract blocks these hours. Review its work schedule before hiring.');
        $this->assertDatabaseHas('job_applications', ['id' => $second->id, 'status' => 'viewed']);
        $third = JobApplication::create(['job_id' => $this->job([$this->slot('Mon', '12:00', '13:00')])->id, 'student_id' => 1, 'status' => 'viewed']);
        $this->putJson('/api/employer/applications/'.$third->id.'/status', ['status' => 'accepted'])->assertOk();
        $this->assertSame([$this->slot('Mon', '09:00', '12:00')], $first->fresh()->work_schedule_slots);
    }

    public function test_availability_mismatch_warns_before_applying_and_does_not_block_hiring(): void
    {
        $this->availability([$this->slot('Mon', '08:00', '12:00'), $this->slot('Mon', '13:00', '17:00')]);
        $job = $this->job([$this->slot('Mon', '10:00', '14:00')]);
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id])->assertStatus(409)
            ->assertJsonPath('requires_schedule_confirmation', true)
            ->assertJsonPath('schedule_review.required_slots', $job->schedule_slots)
            ->assertJsonPath('schedule_review.availability_slots', Student::findOrFail(1)->schedule_slots);
        $this->assertDatabaseCount('job_applications', 0);
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id, 'acknowledge_schedule_warning' => true])->assertCreated();
        $a = JobApplication::firstOrFail();
        $this->login(2);
        $this->putJson('/api/employer/applications/'.$a->id.'/status', ['status' => 'accepted'])->assertOk()
            ->assertJsonPath('application.work_schedule_slots', $job->schedule_slots);
    }

    public function test_hiring_allows_extra_available_hours_and_ignores_cancelled_applications(): void
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        $this->availability(array_map(fn ($day) => $this->slot($day, '06:00', '17:00'), $days));
        $hours = array_map(fn ($day) => $this->slot($day, '06:00', '12:00'), $days);
        $job = $this->job($hours);
        $cancelled = JobApplication::create(['job_id' => $job->id, 'student_id' => 1, 'status' => 'cancelled', 'work_schedule_slots' => $hours]);
        $current = JobApplication::create(['job_id' => $job->id, 'student_id' => 1, 'status' => 'viewed']);
        $this->login(2);
        $this->putJson('/api/employer/applications/'.$current->id.'/status', ['status' => 'accepted'])->assertOk()
            ->assertJsonPath('application.work_schedule_slots', $hours);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('accepted', $current->fresh()->status);
    }

    public function test_missing_availability_requires_confirmation_before_applying(): void
    {
        Student::findOrFail(1)->update(['available_days' => [], 'time_slot' => null]);
        $job = $this->job([$this->slot()]);
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id])->assertStatus(409)
            ->assertJsonPath('schedule_review.availability_slots', []);
        $this->assertDatabaseCount('job_applications', 0);
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id, 'acknowledge_schedule_warning' => true])->assertCreated();
    }

    public function test_extra_available_hours_need_no_confirmation_and_an_acknowledgement_cannot_bypass_contract_conflicts(): void
    {
        $this->availability([$this->slot('Mon', '06:00', '17:00')]);
        $job = $this->job([$this->slot('Mon', '06:00', '12:00')]);
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id])->assertCreated();
        $first = JobApplication::firstOrFail();
        $this->login(2);
        $this->putJson('/api/employer/applications/'.$first->id.'/status', ['status' => 'accepted'])->assertOk();
        $secondJob = $this->job([$this->slot('Mon', '11:00', '13:00')]);
        $this->login(1);
        $this->postJson('/api/student/apply-job', ['job_id' => $secondJob->id])->assertStatus(409)
            ->assertJsonFragment(['message' => 'These work hours overlap an active contract, or its hours are not confirmed. You can still apply, but the contract conflict must be resolved before you can be hired.']);
        $this->postJson('/api/student/apply-job', ['job_id' => $secondJob->id, 'acknowledge_schedule_warning' => true])->assertCreated();
        $second = JobApplication::where('job_id', $secondJob->id)->firstOrFail();
        // Even after changing availability, a reserved contract hour remains blocked.
        $this->availability([$this->slot('Tue', '06:00', '12:00')]);
        $this->login(2);
        $this->putJson('/api/employer/applications/'.$second->id.'/status', ['status' => 'accepted'])->assertUnprocessable()
            ->assertJsonPath('errors.work_schedule.0', 'An active contract blocks these hours. Review its work schedule before hiring.');
        $this->assertDatabaseHas('job_applications', ['id' => $second->id, 'status' => 'pending', 'hired_at' => null]);
    }

    public function test_warning_confirmation_does_not_bypass_verification_or_duplicate_checks(): void
    {
        $this->availability([$this->slot('Tue')]);
        $job = $this->job([$this->slot()]);
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id, 'acknowledge_schedule_warning' => 'yes'])->assertUnprocessable();
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id, 'acknowledge_schedule_warning' => true])->assertCreated();
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id, 'acknowledge_schedule_warning' => true])->assertStatus(400);
        Student::findOrFail(1)->update(['isVerified' => false]);
        $this->postJson('/api/student/apply-job', ['job_id' => $job->id, 'acknowledge_schedule_warning' => true])->assertForbidden();
        $this->assertDatabaseCount('job_applications', 1);
    }

    public function test_overnight_sunday_rollover_and_touching_ranges_have_exact_coverage(): void
    {
        $this->assertSame('full', WeeklySchedule::compare([$this->slot('Mon', '00:00', '05:00')], [$this->slot('Sun', '22:00', '06:00', true)])['status']);
        $this->assertSame(100, WeeklySchedule::compare([$this->slot('Mon', '09:00', '12:00')], [$this->slot('Mon', '09:00', '10:00'), $this->slot('Mon', '10:00', '12:00')])['percentage']);
        $this->assertSame('unknown', WeeklySchedule::compare([], [$this->slot()])['status']);
    }

    public function test_capabilities_require_all_migrations_and_failed_writes_roll_back_profile_changes(): void
    {
        $this->getJson('/api/schedule-capabilities')->assertOk()->assertJsonPath('weekly_schedule_version', 1);
        Schema::drop('student_availability_slots');
        $this->getJson('/api/schedule-capabilities')->assertOk()->assertJsonPath('weekly_schedule_version', 0);
        $this->postJson('/api/student/update-availability', ['schedule_slots' => [$this->slot('Fri')]])->assertStatus(503);
        $this->assertDatabaseHas('students', ['id' => 1, 'available_days' => '["Mon"]', 'time_slot' => 'Whole Day']);
    }

    public function test_job_edit_preserves_hours_for_an_existing_legacy_contract(): void
    {
        $job = $this->job([$this->slot('Mon', '09:00', '12:00')]);
        $application = JobApplication::create(['job_id' => $job->id, 'student_id' => 1, 'status' => 'accepted']);
        $this->login(2);
        $this->putJson('/api/jobs/'.$job->id, ['title' => 'Assistant', 'description' => 'New shift', 'salary' => 450, 'category' => 'General', 'schedule_slots' => [$this->slot('Wed', '18:00', '22:00')]])->assertOk();
        $this->assertSame([$this->slot('Mon', '09:00', '12:00')], $application->fresh()->work_schedule_slots);
    }

    public function test_unknown_active_contract_is_not_treated_as_free_and_disjoint_dates_do_not_block_hiring(): void
    {
        $this->availability([$this->slot('Mon', '08:00', '17:00')]);
        $first = JobApplication::create(['job_id' => $this->job([$this->slot()])->id, 'student_id' => 1, 'status' => 'accepted', 'work_schedule' => 'As discussed', 'start_date' => '2098-01-01', 'end_date' => '2098-01-31']);
        $second = JobApplication::create(['job_id' => $this->job([$this->slot()])->id, 'student_id' => 1, 'status' => 'viewed']);
        $this->login(2);
        $this->putJson('/api/employer/applications/'.$second->id.'/status', ['status' => 'accepted'])->assertUnprocessable();
        $this->putJson('/api/employer/applications/'.$second->id.'/status', ['status' => 'accepted', 'start_date' => '2098-02-01', 'end_date' => '2098-02-28'])->assertOk();
        $this->assertSame('accepted', $first->fresh()->status);
    }

    public function test_ai_cannot_label_partial_hours_as_a_perfect_overall_match(): void
    {
        $this->availability([$this->slot('Mon', '09:00', '12:00')]);
        $job = $this->job([$this->slot('Mon', '09:00', '15:00')]);
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([['id' => $job->id, 'match_percentage' => 100]])]]]]]])]);
        $this->getJson('/api/student/ai-matched-jobs')->assertOk()->assertJsonPath('matched_jobs.0.match_percentage', 99)
            ->assertJsonPath('matched_jobs.0.schedule_match.status', 'partial')->assertJsonPath('matched_jobs.0.schedule_match.percentage', 50);
    }

    public function test_old_clients_can_edit_job_details_but_cannot_replace_exact_hours(): void
    {
        $job = $this->job([$this->slot('Mon', '09:30', '12:00'), $this->slot('Wed', '22:00', '06:00', true)]);
        $slots = $job->schedule_slots;
        $this->login(2);
        $payload = ['title' => 'Updated title', 'description' => 'Updated details', 'salary' => 500, 'category' => 'General',
            'available_days' => $job->available_days, 'time_slot' => $job->time_slot];
        $this->putJson('/api/jobs/'.$job->id, $payload)->assertOk()->assertJsonPath('job.title', 'Updated title')->assertJsonPath('job.schedule_slots', $slots);
        $payload['available_days'] = ['Sat']; $payload['time_slot'] = 'Whole Day';
        $this->putJson('/api/jobs/'.$job->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('schedule_slots');
        $this->assertSame($slots, $job->fresh()->schedule_slots);
    }

    public function test_unknown_frozen_contract_hours_do_not_change_when_the_listing_changes(): void
    {
        $job = $this->job([$this->slot()]);
        $contract = JobApplication::create(['job_id' => $job->id, 'student_id' => 1, 'status' => 'accepted', 'work_schedule_slots' => []]);
        $job->replaceWeeklySlots([$this->slot('Wed', '18:00', '22:00')]);
        $matcher = app(\App\Services\StudentScheduleMatching::class);
        $this->assertSame([], $matcher->contractSlots($contract->fresh()));
        $this->assertSame(7, count($matcher->busy(Student::find(1))));
    }

    public function test_migration_can_resume_without_erasing_saved_hours_and_rollback_keeps_legacy_columns(): void
    {
        $this->availability([$this->slot()]);
        $job = $this->job([$this->slot('Wed', '18:00', '22:00')]);
        $contract = JobApplication::create(['job_id' => $job->id, 'student_id' => 1, 'status' => 'accepted', 'work_schedule_slots' => $job->schedule_slots]);
        $migration = require database_path('migrations/2026_10_20_180000_create_weekly_schedule_slots.php');
        $migration->up();
        $this->assertDatabaseCount('student_availability_slots', 1);
        $this->assertSame($job->schedule_slots, $contract->fresh()->work_schedule_slots);
        $migration->down();
        $this->assertFalse(Schema::hasTable('student_availability_slots'));
        $this->assertFalse(Schema::hasTable('job_schedule_slots'));
        $this->assertFalse(Schema::hasColumn('job_applications', 'work_schedule_slots'));
        $this->assertDatabaseHas('students', ['id' => 1, 'time_slot' => 'Mon 09:30-15:00']);
        $this->assertDatabaseHas('job_applications', ['id' => $contract->id, 'status' => 'accepted']);
    }

    public function test_ai_receives_reserved_hours_and_job_detail_edits_invalidate_its_cached_score(): void
    {
        $this->availability([$this->slot('Mon', '08:00', '17:00')]);
        $job = $this->job([$this->slot('Mon', '10:00', '14:00')]);
        $booked = [$this->slot('Mon', '11:00', '12:00')];
        JobApplication::create(['job_id' => $job->id, 'student_id' => 1, 'status' => 'accepted', 'work_schedule_slots' => $booked]);
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([['id' => $job->id, 'match_percentage' => 80]])]]]]]])]);
        $this->getJson('/api/student/ai-matched-jobs')->assertOk();
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($booked) {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'];
            return str_contains($prompt, '- Active work hours (already booked): '.json_encode($booked));
        });
        $job->update(['title' => 'Catering assistant', 'category' => 'Food service']);
        $this->getJson('/api/student/ai-matched-jobs')->assertOk();
        Http::assertSentCount(2);
    }
}
