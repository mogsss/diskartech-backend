<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HiringInputValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id');
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('job_id'); $table->string('status');
            foreach (['start_date', 'end_date', 'work_schedule', 'agreed_rate', 'special_instructions', 'contract_terms'] as $field) $table->text($field)->nullable();
            $table->timestamp('hired_at')->nullable(); $table->timestamps();
        });
        DB::table('available_jobs')->insert(['id' => 5, 'user_id' => 1]);
        DB::table('job_applications')->insert(['id' => 27, 'job_id' => 5, 'status' => 'viewed']);
        $this->login('employer');
    }

    private function login(string $role, int $id = 1): void
    {
        $user = new User;
        $user->id = $id; $user->role = $role;
        Sanctum::actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'status' => 'accepted', 'start_date' => 'October 15, 2026', 'end_date' => 'October 30, 2026',
            'work_schedule' => 'Mon, Wed • Morning (6AM - 12PM)', 'agreed_rate' => '250.50',
            'contract_terms' => '• Give 3 days’ notice.', 'special_instructions' => 'Bring an ID.',
        ], $overrides);
    }

    public function test_both_hirer_roles_save_normalized_plain_text_without_losing_unicode_or_line_breaks(): void
    {
        foreach (['employer', 'household'] as $role) {
            $this->login($role);
            $this->putJson('/api/employer/applications/27/status', $this->payload([
                'special_instructions' => "  Bring Juan's ID.\r\nMagdala ng résumé.\x00\u{202E}  ",
                'contract_terms' => "  • Give 3 days’ notice.\r\nPayment after work.  ", 'agreed_rate' => ' 250.50 ',
            ]))->assertOk()->assertJsonPath('application.agreed_rate', '250.50')
                ->assertJsonPath('application.special_instructions', "Bring Juan's ID.\nMagdala ng résumé.")
                ->assertJsonPath('application.contract_terms', "• Give 3 days’ notice.\nPayment after work.");
        }
    }

    public static function invalidInputs(): array
    {
        return [
            'script' => ['special_instructions', '<script>alert(1)</script>'],
            'encoded script' => ['contract_terms', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'html schedule' => ['work_schedule', '<img src=x onerror=alert(1)>'],
            'array' => ['special_instructions', ['unexpected']],
            'too long instructions' => ['special_instructions', str_repeat('a', 2001)],
            'too long terms' => ['contract_terms', str_repeat('a', 2001)],
            'too long schedule' => ['work_schedule', str_repeat('a', 256)],
            'blank terms' => ['contract_terms', "\x00  "],
            'negative' => ['agreed_rate', '-1'],
            'zero' => ['agreed_rate', '0.00'],
            'exponent' => ['agreed_rate', '1e3'],
            'excess decimals' => ['agreed_rate', '250.999'],
            'multiple decimals' => ['agreed_rate', '2.5.0'],
            'oversize rate' => ['agreed_rate', '1234567890'],
            'invalid calendar date' => ['start_date', 'February 30, 2026'],
            'relative date' => ['start_date', 'tomorrow'],
            'end before start' => ['end_date', 'October 14, 2026'],
            'invalid end date' => ['end_date', 'not a date'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_payloads_are_rejected_without_mutating_the_application(string $field, mixed $value): void
    {
        $this->putJson('/api/employer/applications/27/status', $this->payload([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseHas('job_applications', ['id' => 27, 'status' => 'viewed', 'hired_at' => null, 'agreed_rate' => null]);
    }

    public function test_project_based_end_dates_and_iso_dates_remain_supported(): void
    {
        foreach (['Until Project Completion', 'Indefinite / Project-based', '2026-10-30', null] as $end) {
            $this->putJson('/api/employer/applications/27/status', $this->payload(['start_date' => '2026-10-15', 'end_date' => $end]))->assertOk();
        }
    }

    public function test_partial_updates_cannot_set_end_before_the_stored_start(): void
    {
        DB::table('job_applications')->where('id', 27)->update(['start_date' => 'October 15, 2026']);
        $this->putJson('/api/employer/applications/27/status', ['status' => 'accepted', 'end_date' => 'October 14, 2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->assertDatabaseHas('job_applications', ['id' => 27, 'status' => 'viewed', 'end_date' => null]);
    }

    public function test_valid_payload_cannot_bypass_owner_permission(): void
    {
        $this->login('employer', 9);
        $this->putJson('/api/employer/applications/27/status', $this->payload())->assertForbidden();
        $this->assertDatabaseHas('job_applications', ['id' => 27, 'status' => 'viewed']);
    }
}
