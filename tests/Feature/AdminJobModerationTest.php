<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminJobModerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('email'); $table->string('role'); $table->timestamps(); });
        foreach (['employers' => ['employer_name', 'hirer_name', 'contact_number'], 'households' => ['household_name', 'cp_number']] as $name => $fields) {
            Schema::create($name, function (Blueprint $table) use ($fields) {
                $table->id(); $table->unsignedBigInteger('user_id');
                foreach ($fields as $field) $table->string($field)->nullable();
                $table->text('location')->nullable(); $table->text('detailed_address')->nullable(); $table->boolean('isVerified')->default(false);
            });
        }
        Schema::create('students', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->string('student_name');
            $table->string('student_school_name')->nullable(); $table->string('course')->nullable();
        });
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('title'); $table->text('description')->nullable();
            $table->decimal('salary', 10, 2); $table->string('salary_type')->default('day'); $table->string('status')->default('active');
            $table->string('category')->nullable(); $table->text('available_days')->nullable(); $table->string('time_slot')->nullable();
            $table->json('requirements')->nullable(); $table->json('skills')->nullable(); $table->timestamps();
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('job_id'); $table->unsignedBigInteger('student_id'); $table->string('status')->default('Pending'); $table->timestamps();
        });
        $admin = new Admin; $admin->id = 1; $admin->name = 'Test Administrator';
        $this->actingAs($admin, 'admin');
    }

    private function account(string $role = 'household', string $name = 'Example household'): int
    {
        $id = DB::table('users')->insertGetId(['email' => 'hirer'.$role.'@example.com', 'role' => $role]);
        DB::table($role.'s')->insert([
            'user_id' => $id,
            ...($role === 'employer' ? ['employer_name' => $name, 'hirer_name' => 'Business hirer', 'contact_number' => '09111111111'] : ['household_name' => $name, 'cp_number' => '09222222222']),
            'location' => 'Pinamalayan', 'detailed_address' => 'Example street, Pinamalayan, Oriental Mindoro',
            'isVerified' => true,
        ]);
        return $id;
    }

    private function job(int $user, array $overrides = []): int
    {
        return DB::table('available_jobs')->insertGetId([
            'user_id' => $user, 'title' => 'House cleaning', 'description' => 'Careful cleaning work.',
            'salary' => 125.50, 'salary_type' => 'hour', 'status' => 'active', 'category' => 'Cleaning',
            'available_days' => json_encode(json_encode(['Mon', 'Wed'])), 'time_slot' => 'Morning (6AM–12PM), Afternoon (12PM–6PM)',
            'requirements' => json_encode(json_encode(['School ID'])), 'skills' => json_encode(['Cleaning', '', ['bad' => 'data']]),
            'created_at' => '2026-10-11 01:00:00', 'updated_at' => '2026-10-11 01:00:00',
            ...$overrides,
        ]);
    }

    public function test_global_totals_and_stable_newest_order_without_loading_applicants(): void
    {
        $user = $this->account();
        $oldest = $this->job($user);
        $hidden = $this->job($user, ['status' => 'hidden']);
        $closed = $this->job($user, ['status' => 'closed']);
        $newest = $this->job($user, ['created_at' => '2026-10-11 02:00:00']);
        $response = $this->get('/admin/job-moderation')->assertOk()->assertSee('Job Moderation')->assertSee('₱125.50')->assertSee('/ hr');
        $this->assertSame([$newest, $closed, $hidden, $oldest], $response->viewData('jobs')->pluck('id')->all());
        $this->assertSame(['total' => 4, 'live' => 2, 'hidden' => 1, 'closed' => 1, 'applications' => 0], $response->viewData('stats'));
        foreach ($response->viewData('jobs') as $job) $this->assertFalse($job->relationLoaded('applications'));
    }

    public function test_status_category_search_filters_and_literal_wildcards(): void
    {
        $user = $this->account('employer', '100% Store');
        $target = $this->job($user, ['status' => 'hidden', 'title' => 'Inventory assistant', 'category' => 'Retail']);
        $other = $this->account('household', '1000 Store');
        $this->job($other);
        foreach (['100%', 'Business hirer', 'Inventory', 'hireremployer@example.com'] as $term) {
            $response = $this->get('/admin/job-moderation?'.http_build_query(['search' => $term, 'status' => 'hidden', 'category' => 'Retail']))->assertOk();
            $this->assertSame([$target], $response->viewData('jobs')->pluck('id')->all());
            $this->assertSame(2, $response->viewData('stats')['total']);
        }
        $this->assertSame(0, $this->get('/admin/job-moderation?status=closed')->viewData('jobs')->total());
        $this->assertSame(['Cleaning', 'Retail'], $this->get('/admin/job-moderation')->viewData('categories')->all());
    }

    public function test_filter_validation_and_plain_text_normalization(): void
    {
        foreach (['status' => 'deleted', 'category' => '<b>Cleaning</b>', 'search' => '&lt;script&gt;alert(1)&lt;/script&gt;', 'page' => '-1'] as $field => $value) {
            $path = $field === 'page' ? '/admin/job-moderation/1' : '/admin/job-moderation';
            $this->getJson($path.'?'.http_build_query([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $user = $this->account();
        $this->job($user);
        $this->get('/admin/job-moderation?search='.urlencode(" \x01House cleaning "))->assertOk()->assertViewHas('search', 'House cleaning');
    }

    public function test_details_correct_household_fields_legacy_lists_rates_and_missing_profiles(): void
    {
        $user = $this->account();
        // A stale employer profile must not replace the actual household's name or phone.
        DB::table('employers')->insert(['user_id' => $user, 'employer_name' => 'Wrong business', 'contact_number' => '000', 'isVerified' => false]);
        $id = $this->job($user);
        $this->getJson("/admin/job-moderation/{$id}")->assertOk()
            ->assertJsonPath('job.poster.name', 'Example household')->assertJsonPath('job.poster.phone', '09222222222')
            ->assertJsonPath('job.poster.location', 'Example street, Pinamalayan, Oriental Mindoro')
            ->assertJsonPath('job.poster.verified', true)->assertJsonPath('job.rate', '₱125.50 / hr')
            ->assertJsonPath('job.available_days', ['Mon', 'Wed'])->assertJsonPath('job.requirements', ['School ID'])
            ->assertJsonPath('job.skills', ['Cleaning'])->assertJsonPath('pagination.total', 0);
        DB::table('available_jobs')->where('id', $id)->update(['salary_type' => 'day', 'available_days' => 'bad json']);
        DB::table('households')->delete();
        $this->getJson("/admin/job-moderation/{$id}")->assertOk()->assertJsonPath('job.rate', '₱125.50 / day')
            ->assertJsonPath('job.available_days', [])->assertJsonPath('job.poster.phone', 'Not provided')->assertJsonPath('job.poster.verified', false);
        $this->getJson('/admin/job-moderation/999')->assertNotFound();
    }

    public function test_applicant_pagination_and_safe_listing_preview(): void
    {
        $user = $this->account();
        $id = $this->job($user, ['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>']);
        $this->job($this->account('employer', 'Example business'), ['title' => 'Inventory assistant', 'salary_type' => 'day', 'status' => 'hidden', 'category' => 'Retail']);
        $this->job($user, ['title' => 'Garden assistant', 'status' => 'closed', 'category' => 'Gardening']);
        $student = DB::table('students')->insertGetId(['student_name' => "Ana O'Neil", 'student_school_name' => 'Example school', 'course' => 'BSIT']);
        $ids = [];
        for ($i = 0; $i < 11; $i++) {
            $ids[] = DB::table('job_applications')->insertGetId(['job_id' => $id, 'student_id' => $student, 'status' => 'Pending',
                'created_at' => '2026-10-11 01:00:00', 'updated_at' => '2026-10-11 01:00:00']);
        }
        $response = $this->get('/admin/job-moderation')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $first = $this->getJson("/admin/job-moderation/{$id}")->assertOk()->assertJsonCount(10, 'applicants')
            ->assertJsonPath('pagination.total', 11)->assertJsonPath('pagination.last', 2)->assertJsonPath('job.applications_count', 11);
        $this->assertSame(array_reverse(array_slice($ids, 1)), array_column($first->json('applicants'), 'id'));
        $second = $this->getJson("/admin/job-moderation/{$id}?page=2")->assertOk()->assertJsonCount(1, 'applicants')->assertJsonPath('applicants.0.id', $ids[0]);
        if ($path = getenv('DISKARTECH_JOBS_PREVIEW_PATH')) {
            file_put_contents($path, $response->getContent());
            file_put_contents($path.'.json', $first->getContent());
            file_put_contents($path.'.page2.json', $second->getContent());
        }
    }

    public function test_job_pagination_preserves_filters_and_empty_state(): void
    {
        $this->get('/admin/job-moderation')->assertOk()->assertSee('No job posts yet');
        $user = $this->account();
        for ($i = 0; $i < 12; $i++) $this->job($user);
        $response = $this->get('/admin/job-moderation?status=active&category=Cleaning&search=House')->assertOk();
        $this->assertCount(10, $response->viewData('jobs'));
        $this->assertSame(12, $response->viewData('jobs')->total());
        $this->assertStringContainsString('category=Cleaning', $response->viewData('jobs')->nextPageUrl());
        $this->assertCount(2, $this->get('/admin/job-moderation?status=active&page=2')->viewData('jobs'));
        $this->get('/admin/job-moderation?status=closed')->assertOk()->assertSee('No matching job posts');
    }

    public function test_hide_publish_close_and_explicit_reopen_and_invalid_status(): void
    {
        $id = $this->job($this->account());
        $this->post("/admin/job-moderation/{$id}/toggle")->assertRedirect();
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'status' => 'hidden']);
        $this->post("/admin/job-moderation/{$id}/toggle")->assertRedirect();
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'status' => 'active']);
        $this->patch("/admin/job-moderation/{$id}/status", ['status' => 'closed'])->assertRedirect()->assertSessionHas('success');
        $this->post("/admin/job-moderation/{$id}/toggle")->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'status' => 'closed']);
        $this->patchJson("/admin/job-moderation/{$id}/status", ['status' => 'deleted'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patch("/admin/job-moderation/{$id}/status", ['status' => 'active'])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('available_jobs', ['id' => $id, 'status' => 'active']);
    }

    public function test_delete_removes_only_the_target_job_and_applications(): void
    {
        $user = $this->account(); $id = $this->job($user); $other = $this->job($user);
        foreach ([$id, $other] as $job) DB::table('job_applications')->insert(['job_id' => $job, 'student_id' => 1]);
        $this->delete("/admin/job-moderation/{$id}")->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('available_jobs', ['id' => $id]); $this->assertDatabaseMissing('job_applications', ['job_id' => $id]);
        $this->assertDatabaseHas('available_jobs', ['id' => $other]); $this->assertDatabaseHas('job_applications', ['job_id' => $other]);
    }

    public function test_all_job_moderation_endpoints_require_admin_authentication(): void
    {
        auth('admin')->logout();
        foreach (['GET' => '/admin/job-moderation/1', 'POST' => '/admin/job-moderation/1/toggle', 'PATCH' => '/admin/job-moderation/1/status', 'DELETE' => '/admin/job-moderation/1'] as $method => $path) {
            $this->call($method, $path)->assertRedirect(route('login'));
        }
        $this->get('/admin/job-moderation')->assertRedirect(route('login'));
    }
}
