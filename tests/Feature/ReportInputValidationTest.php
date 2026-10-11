<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportInputValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) { $table->id(); });
        Schema::create('available_jobs', function (Blueprint $table) { $table->id(); });
        Schema::create('reports', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('reporter_id');
            $table->unsignedBigInteger('reported_user_id')->nullable(); $table->unsignedBigInteger('job_id')->nullable();
            $table->string('report_type'); $table->string('subject'); $table->text('description');
            $table->string('status'); $table->timestamps();
        });
        DB::table('users')->insert([['id' => 1], ['id' => 2]]);
        DB::table('available_jobs')->insert(['id' => 27]);
        $user = new User;
        $user->id = 1; $user->role = 'student';
        Sanctum::actingAs($user);
    }

    private function payload(): array
    {
        return ['job_id' => '27', 'reported_user_id' => '2', 'report_type' => 'unsafe', 'subject' => 'Unsafe Workplace - Test job', 'description' => 'A workplace safety concern.'];
    }

    public function test_reports_preserve_targets_and_sanitize_ordinary_text(): void
    {
        $response = $this->postJson('/api/reports', array_replace($this->payload(), [
            'subject' => "  Unsafe Workplace\x00  ", 'description' => "  Safety concern\r\nSecond line\x00\u{202E}  ",
        ]))->assertCreated()->assertJsonPath('status', 'success')
            ->assertJsonPath('report.subject', 'Unsafe Workplace')
            ->assertJsonPath('report.description', "Safety concern\nSecond line")
            ->assertJsonPath('report.reporter_id', 1)->assertJsonPath('report.status', 'pending');
        $this->assertDatabaseHas('reports', ['id' => $response->json('report.id'), 'job_id' => 27, 'reported_user_id' => 2]);
    }

    public function test_existing_open_report_is_not_submitted_twice(): void
    {
        $this->postJson('/api/reports', $this->payload())->assertCreated();
        $this->postJson('/api/reports', $this->payload())->assertOk()->assertJsonPath('status', 'already_reported');
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_general_platform_concern_can_be_submitted_without_a_target(): void
    {
        $this->postJson('/api/reports', array_replace($this->payload(), ['job_id' => null, 'reported_user_id' => null, 'report_type' => 'other']))
            ->assertCreated()->assertJsonPath('report.job_id', null)->assertJsonPath('report.reported_user_id', null);
    }

    public function test_general_concern_is_not_blocked_by_an_open_job_report(): void
    {
        $this->postJson('/api/reports', $this->payload())->assertCreated();
        $this->postJson('/api/reports', ['report_type' => 'other', 'description' => 'A separate platform concern.'])
            ->assertCreated()->assertJsonPath('report.reporter_id', 1)
            ->assertJsonPath('report.job_id', null)->assertJsonPath('report.reported_user_id', null);
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_different_general_concerns_are_allowed_but_identical_open_concerns_are_not(): void
    {
        $first = ['report_type' => 'other', 'description' => 'First concern.'];
        $second = ['report_type' => 'other', 'description' => 'A different concern.'];
        $this->postJson('/api/reports', $first)->assertCreated();
        $this->postJson('/api/reports', $second)->assertCreated();
        $this->postJson('/api/reports', array_replace($first, ['description' => "  First concern.\x00  "]))
            ->assertOk()->assertJsonPath('status', 'already_reported');
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_general_concern_does_not_block_reporting_a_specific_job(): void
    {
        $this->postJson('/api/reports', ['report_type' => 'other', 'description' => 'General concern.'])->assertCreated();
        $this->postJson('/api/reports', $this->payload())->assertCreated();
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_resolved_general_concern_can_be_reported_again_if_it_recurs(): void
    {
        $payload = ['report_type' => 'other', 'description' => 'Recurring concern.'];
        $this->postJson('/api/reports', $payload)->assertCreated();
        DB::table('reports')->update(['status' => 'resolved']);
        $this->postJson('/api/reports', $payload)->assertCreated();
        $this->assertDatabaseCount('reports', 2);
    }

    public static function invalidReports(): array
    {
        return [
            'whitespace explanation' => ['description', " \r\n\t\x00 "],
            'too long explanation' => ['description', str_repeat('a', 2001)],
            'HTML explanation' => ['description', '<script>alert(1)</script>'],
            'encoded HTML' => ['description', '&lt;img src=x onerror=alert(1)&gt;'],
            'HTML subject' => ['subject', '<b>Unsafe</b>'],
            'array explanation' => ['description', ['invalid']],
            'unknown reason' => ['report_type', 'invented'],
            'invalid job ID' => ['job_id', '27 OR 1=1'],
            'missing job' => ['job_id', 999],
            'invalid user ID' => ['reported_user_id', '<script>2</script>'],
            'missing user' => ['reported_user_id', 999],
        ];
    }

    #[DataProvider('invalidReports')]
    public function test_invalid_inputs_are_rejected_without_saving(string $field, mixed $value): void
    {
        $this->postJson('/api/reports', array_replace($this->payload(), [$field => $value]))->assertUnprocessable();
        $this->assertDatabaseCount('reports', 0);
    }
}
