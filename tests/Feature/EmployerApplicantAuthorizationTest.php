<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\JobApplication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployerApplicantAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'uploads.driver' => 'public']);
        Http::preventStrayRequests();
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('role'); });
        Schema::create('students', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('student_name');
            $table->string('location')->nullable();
        });
        foreach (['employers', 'households'] as $name) Schema::create($name, function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id');
            $table->string('location')->nullable(); $table->string('detailed_address')->nullable();
        });
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('title');
            $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('job_id'); $table->unsignedBigInteger('student_id');
            $table->string('status')->default('pending'); $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 1, 'role' => 'employer'], ['id' => 2, 'role' => 'employer'],
            ['id' => 3, 'role' => 'student'], ['id' => 4, 'role' => 'household'],
            ['id' => 5, 'role' => 'household'], ['id' => 6, 'role' => 'admin'],
        ]);
        DB::table('students')->insert(['id' => 22, 'user_id' => 3, 'student_name' => 'Confidential Applicant', 'location' => 'Private student address']);
        // Profile IDs intentionally differ from the authenticated user IDs.
        DB::table('employers')->insert([['id' => 41, 'user_id' => 1, 'location' => 'Owner location'], ['id' => 42, 'user_id' => 2, 'location' => 'Other location']]);
        DB::table('households')->insert([['id' => 44, 'user_id' => 4, 'location' => 'Household location'], ['id' => 45, 'user_id' => 5, 'location' => 'Other household']]);
        DB::table('available_jobs')->insert([
            ['id' => 100, 'user_id' => 1, 'title' => 'Employer opening'],
            ['id' => 101, 'user_id' => 4, 'title' => 'Household opening'],
            ['id' => 102, 'user_id' => 2, 'title' => 'Other opening'],
            ['id' => 103, 'user_id' => 1, 'title' => 'Empty owned opening'],
        ]);
        DB::table('job_applications')->insert([
            ['id' => 200, 'job_id' => 100, 'student_id' => 22],
            ['id' => 201, 'job_id' => 101, 'student_id' => 22],
            ['id' => 202, 'job_id' => 102, 'student_id' => 22],
            ['id' => 203, 'job_id' => 999, 'student_id' => 22],
        ]);
    }

    private function login(int $id): void
    {
        Sanctum::actingAs(User::findOrFail($id));
    }

    public static function owners(): array
    {
        return ['employer' => [1, 100, 200], 'household' => [4, 101, 201]];
    }

    #[DataProvider('owners')]
    public function test_job_owner_can_view_applicants_and_details_and_only_their_application_becomes_viewed(int $user, int $job, int $application): void
    {
        $this->login($user);
        $this->getJson('/api/employer/jobs/'.$job.'/applicants')->assertOk()->assertJsonCount(1, 'applicants')->assertJsonPath('applicants.0.id', $application)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('job_applications', ['id' => $application, 'status' => 'pending']);
        $this->getJson('/api/employer/applications/'.$application)->assertOk()->assertJsonPath('application.status', 'viewed')->assertJsonPath('application.job.user_id', $user)
            ->assertHeader('Cache-Control', 'no-store, private');
        foreach ([200, 201, 202, 203] as $other) {
            if ($other !== $application) $this->assertDatabaseHas('job_applications', ['id' => $other, 'status' => 'pending', 'updated_at' => null]);
        }
    }

    #[DataProvider('owners')]
    public function test_only_job_owner_can_delete_the_application(int $user, int $job, int $application): void
    {
        $this->login($user);
        $this->deleteJson('/api/employer/applications/'.$application)->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseMissing('job_applications', ['id' => $application]);
        $this->assertDatabaseCount('job_applications', 3);
        $this->assertDatabaseHas('available_jobs', ['id' => $job, 'user_id' => $user]);
        $this->assertDatabaseHas('students', ['id' => 22]);
    }

    public function test_other_hirers_cannot_read_or_delete_an_application_even_with_forged_owner_fields(): void
    {
        foreach ([2, 4, 5] as $user) {
            $this->login($user);
            $this->getJson('/api/employer/jobs/100/applicants?user_id=1')->assertNotFound()->assertJsonMissingPath('applicants')->assertDontSee('Confidential Applicant');
            $this->getJson('/api/employer/applications/200?user_id=1&job_id=100')->assertNotFound()->assertJsonMissingPath('application')->assertDontSee('Private student address');
            $this->deleteJson('/api/employer/applications/200?user_id=1', ['job_id' => 100, 'role' => 'employer'])->assertNotFound();
            $this->assertDatabaseHas('job_applications', ['id' => 200, 'status' => 'pending', 'updated_at' => null]);
        }
        $this->assertDatabaseCount('job_applications', 4);
    }

    public function test_household_applications_are_not_accessible_to_other_employers_or_households(): void
    {
        foreach ([1, 2, 5] as $user) {
            $this->login($user);
            $this->getJson('/api/employer/jobs/101/applicants')->assertNotFound();
            $this->getJson('/api/employer/applications/201')->assertNotFound();
            $this->deleteJson('/api/employer/applications/201')->assertNotFound();
        }
        $this->assertDatabaseHas('job_applications', ['id' => 201, 'status' => 'pending', 'updated_at' => null]);
    }

    public function test_student_applicant_and_non_hirer_accounts_cannot_use_employer_endpoints(): void
    {
        foreach ([3, 6] as $user) {
            $this->login($user);
            foreach (['/api/employer/jobs/100/applicants', '/api/employer/applications/200', '/api/employer/applicants', '/api/employer/jobs'] as $path) {
                $this->getJson($path)->assertForbidden()->assertDontSee('Confidential Applicant');
            }
            $this->deleteJson('/api/employer/applications/200')->assertForbidden();
        }
        $this->assertDatabaseHas('job_applications', ['id' => 200, 'status' => 'pending', 'updated_at' => null]);
    }

    public function test_authentication_is_required_before_application_access(): void
    {
        foreach (['/api/employer/jobs/100/applicants', '/api/employer/applications/200', '/api/employer/applicants', '/api/employer/jobs'] as $path) $this->getJson($path)->assertUnauthorized();
        $this->deleteJson('/api/employer/applications/200')->assertUnauthorized();
        $this->assertDatabaseCount('job_applications', 4);
    }

    public function test_missing_or_orphaned_records_are_not_found_and_an_owned_empty_job_is_allowed(): void
    {
        $this->login(1);
        $this->getJson('/api/employer/jobs/103/applicants')->assertOk()->assertJsonCount(0, 'applicants');
        $this->getJson('/api/employer/jobs/999/applicants')->assertNotFound();
        foreach ([203, 999] as $id) {
            $this->getJson('/api/employer/applications/'.$id)->assertNotFound();
            $this->deleteJson('/api/employer/applications/'.$id)->assertNotFound();
        }
        $this->assertDatabaseHas('job_applications', ['id' => 203, 'status' => 'pending']);
    }

    public function test_reading_details_does_not_change_an_existing_hiring_or_interview_status(): void
    {
        $this->login(1);
        foreach (['viewed', 'accepted', 'interview', 'completed', 'terminated', 'rejected', 'cancelled'] as $status) {
            DB::table('job_applications')->where('id', 200)->update(['status' => $status, 'updated_at' => null]);
            $this->getJson('/api/employer/applications/200')->assertOk()->assertJsonPath('application.status', $status);
            $this->assertDatabaseHas('job_applications', ['id' => 200, 'status' => $status, 'updated_at' => null]);
        }
    }

    public function test_a_hiring_update_during_details_loading_is_not_overwritten_with_viewed(): void
    {
        $this->login(1);
        $changed = false;
        JobApplication::retrieved(function ($application) use (&$changed) {
            if (!$changed && $application->id === 200 && $application->status === 'pending') {
                $changed = true;
                DB::table('job_applications')->where('id', 200)->update(['status' => 'accepted']);
            }
        });
        $this->getJson('/api/employer/applications/200')->assertOk()->assertJsonPath('application.status', 'accepted');
        $this->assertTrue($changed);
        $this->assertDatabaseHas('job_applications', ['id' => 200, 'status' => 'accepted']);
    }

    #[DataProvider('owners')]
    public function test_aggregate_lists_include_only_the_current_hirers_jobs_and_applications(int $user, int $job, int $application): void
    {
        $this->login($user);
        $this->getJson('/api/employer/applicants')->assertOk()->assertJsonCount(1, 'applicants')->assertJsonPath('applicants.0.id', $application);
        $jobs = $this->getJson('/api/employer/jobs')->assertOk()->json('jobs');
        foreach ($jobs as $entry) $this->assertSame($user, $entry['user_id']);
    }
}
