<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('email'); $table->string('role'); $table->timestamps(); });
        foreach (['students' => ['student_name'], 'employers' => ['employer_name', 'hirer_name'], 'households' => ['household_name']] as $name => $fields) {
            Schema::create($name, function (Blueprint $table) use ($fields) {
                $table->id(); $table->unsignedBigInteger('user_id');
                foreach ($fields as $field) $table->string($field)->nullable();
            });
        }
        Schema::create('available_jobs', function (Blueprint $table) { $table->id(); $table->string('title'); });
        Schema::create('job_applications', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('job_id'); });
        Schema::create('reviews', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('application_id'); $table->unsignedBigInteger('reviewer_id');
            $table->unsignedBigInteger('reviewee_id'); $table->string('reviewer_role'); $table->unsignedTinyInteger('rating');
            $table->text('feedback')->nullable(); $table->json('tags')->nullable(); $table->timestamps();
        });
        $admin = new Admin; $admin->id = 1; $admin->name = 'Test Administrator';
        $this->actingAs($admin, 'admin');
    }

    private function account(string $role, string $name): int
    {
        $id = DB::table('users')->insertGetId(['email' => 'person-'.$role.'-'.$name.'@example.com', 'role' => $role]);
        $fields = match ($role) {
            'student' => ['student_name' => $name],
            'employer' => ['employer_name' => $name, 'hirer_name' => 'Hirer '.$name],
            'household' => ['household_name' => $name],
        };
        DB::table($role.'s')->insert(['user_id' => $id, ...$fields]);
        return $id;
    }

    private function review(int $from, int $to, int $rating, ?string $feedback = 'Helpful feedback.', string $title = 'Example job', array $tags = []): int
    {
        $job = DB::table('available_jobs')->insertGetId(['title' => $title]);
        $application = DB::table('job_applications')->insertGetId(['job_id' => $job]);
        return DB::table('reviews')->insertGetId([
            'application_id' => $application, 'reviewer_id' => $from, 'reviewee_id' => $to,
            'reviewer_role' => 'employer', 'rating' => $rating, 'feedback' => $feedback, 'tags' => json_encode($tags),
            'created_at' => '2026-10-11 01:00:00', 'updated_at' => '2026-10-11 01:00:00',
        ]);
    }

    public function test_real_totals_and_distribution_and_newest_order(): void
    {
        $student = $this->account('student', 'Student');
        $employer = $this->account('employer', 'Business');
        $ids = [];
        foreach ([1, 2, 3, 4, 5] as $rating) $ids[] = $this->review($employer, $student, $rating);
        DB::table('reviews')->where('id', $ids[0])->update(['created_at' => '2026-10-11 03:00:00']);
        $response = $this->get('/admin/reviews')->assertOk()->assertViewHas('totalReviews', 5)
            ->assertViewHas('averageRating', 3.0)->assertViewHas('lowRatingCount', 2);
        $this->assertSame([$ids[0], $ids[4], $ids[3], $ids[2], $ids[1]], $response->viewData('reviews')->pluck('id')->all());
        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1], $response->viewData('distribution')->sortKeys()->map(fn ($count) => (int) $count)->all());
    }

    public function test_filters_use_recipient_role_including_household_and_exact_rating(): void
    {
        $student = $this->account('student', 'Student');
        $household = $this->account('household', 'Household');
        $employer = $this->account('employer', 'Business');
        $target = $this->review($student, $household, 2, 'Respectful', 'Housework');
        $this->review($household, $student, 2);
        $this->review($student, $employer, 5);
        $response = $this->get('/admin/reviews?role=household&rating=low')->assertOk()->assertViewHas('totalReviews', 3);
        $this->assertSame([$target], $response->viewData('reviews')->pluck('id')->all());
        $this->assertSame(1, $this->get('/admin/reviews?role=employer&rating=5')->viewData('reviews')->total());
        $this->assertSame(0, $this->get('/admin/reviews?role=household&rating=5')->viewData('reviews')->total());
    }

    public function test_search_matches_feedback_job_and_both_parties_and_escapes_wildcards(): void
    {
        $student = $this->account('student', 'Ana');
        $employer = $this->account('employer', '100% Store');
        $wanted = $this->review($employer, $student, 4, 'Careful worker', 'Inventory assistant');
        $other = $this->account('employer', '1000 Store');
        $this->review($other, $student, 3, 'Okay', 'Cleaning');
        foreach (['100%', 'Careful', 'Inventory', 'Hirer 100% Store'] as $term) {
            $response = $this->get('/admin/reviews?search='.urlencode($term))->assertOk();
            $this->assertSame([$wanted], $response->viewData('reviews')->pluck('id')->all());
        }
        $this->assertSame(2, $this->get('/admin/reviews?search=Ana')->viewData('reviews')->total());
    }

    public function test_reviews_are_escaped_and_missing_relations_do_not_break_the_page(): void
    {
        $student = $this->account('student', "Ana O'Neil");
        $household = $this->account('household', 'Household');
        $this->review($household, $student, 2, '<script>alert(1)</script>', 'House cleaning', ['Punctual', '<b>tag</b>', ['unexpected' => 'object']]);
        $this->review($student, $household, 5, 'Clear instructions and fair working conditions.', 'Garden assistant', ['Respectful']);
        $this->review(999, 998, 3, null);
        $response = $this->get('/admin/reviews')->assertOk()->assertSee('Household')->assertSee('Punctual')
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>tag</b>', false)
            ->assertSee('Account no longer available')->assertSee('No written feedback provided.');
        if ($path = getenv('DISKARTECH_REVIEWS_PREVIEW_PATH')) file_put_contents($path, $response->getContent());
    }

    public function test_pagination_keeps_filters_and_total_counts(): void
    {
        $student = $this->account('student', 'Student');
        $employer = $this->account('employer', 'Business');
        for ($i = 0; $i < 14; $i++) $this->review($employer, $student, 4);
        $response = $this->get('/admin/reviews?role=student&rating=4&search=Helpful')->assertOk();
        $this->assertCount(12, $response->viewData('reviews'));
        $this->assertSame(14, $response->viewData('reviews')->total());
        $this->assertStringContainsString('role=student', $response->viewData('reviews')->nextPageUrl());
        $this->assertCount(2, $this->get('/admin/reviews?role=student&rating=4&search=Helpful&page=2')->viewData('reviews'));
    }

    public function test_empty_state_has_no_fabricated_rating_and_invalid_filters_are_rejected(): void
    {
        $this->get('/admin/reviews')->assertOk()->assertViewHas('averageRating', null)->assertViewHas('totalReviews', 0)->assertSee('No reviews yet');
        foreach (['role' => 'admin', 'rating' => '6', 'search' => '<script>bad</script>'] as $field => $value) {
            $this->getJson('/admin/reviews?'.http_build_query([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_reviews_require_admin_authentication(): void
    {
        auth('admin')->logout();
        $this->get('/admin/reviews')->assertRedirect(route('login'));
    }
}
