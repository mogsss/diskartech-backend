<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleAdminSession::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('email'); $table->string('role'); $table->string('password')->nullable(); $table->timestamps(); });
        foreach (['students' => ['student_name', 'student_school_name', 'course', 'year_level', 'contact_number'], 'employers' => ['hirer_name', 'employer_name', 'business_type', 'contact_number'], 'households' => ['household_name', 'cp_number']] as $name => $fields) {
            Schema::create($name, function (Blueprint $table) use ($fields) {
                $table->id(); $table->unsignedBigInteger('user_id');
                foreach ($fields as $field) $table->string($field)->nullable();
                $table->boolean('isVerified')->nullable()->default(false);
                $table->text('location')->nullable(); $table->text('detailed_address')->nullable();
            });
        }
        $admin = new Admin; $admin->id = 1; $admin->name = 'Test Administrator';
        $this->actingAs($admin, 'admin');
    }

    private function account(string $role = 'student', string $name = 'Example student', ?bool $verified = false, bool $withProfile = true): int
    {
        $id = DB::table('users')->insertGetId(['email' => 'account-'.uniqid().'@example.com', 'role' => $role, 'password' => 'private-test-password-hash',
            'created_at' => '2026-10-11 01:00:00', 'updated_at' => '2026-10-11 01:00:00']);
        if ($withProfile) DB::table($role.'s')->insert([
            'user_id' => $id, 'isVerified' => $verified, 'location' => 'Pinamalayan', 'detailed_address' => 'Example street, Pinamalayan, Oriental Mindoro',
            ...match ($role) {
                'student' => ['student_name' => $name, 'student_school_name' => 'Example school', 'course' => 'BSIT', 'year_level' => '4th Year', 'contact_number' => '09111111111'],
                'employer' => ['hirer_name' => $name, 'employer_name' => '100% Store', 'business_type' => 'Retail', 'contact_number' => '09222222222'],
                'household' => ['household_name' => $name, 'cp_number' => '09333333333'],
            },
        ]);
        return $id;
    }

    public function test_real_totals_correct_labels_and_newest_order(): void
    {
        $ids = [$this->account('student', 'Student', true), $this->account('employer', 'Hirer'), $this->account('household', 'Household')];
        DB::table('users')->where('id', $ids[0])->update(['created_at' => '2026-10-11 02:00:00']);
        $response = $this->get('/admin/users')->assertOk()->assertViewHas('totalAccounts', 3)->assertViewHas('verifiedAccounts', 1)
            ->assertSee('Verified')->assertSee('Unverified')->assertSee('View details')->assertDontSee('>Active<', false)->assertDontSee('private-test-password-hash', false);
        $this->assertSame([$ids[0], $ids[2], $ids[1]], $response->viewData('users')->pluck('id')->all());
        $this->assertSame(['employer' => 1, 'household' => 1, 'student' => 1], $response->viewData('roleCounts')->sortKeys()->map(fn ($value) => (int) $value)->all());
    }

    public function test_filters_use_actual_role_and_include_unfinished_profiles(): void
    {
        $student = $this->account('student', 'Student', true);
        $household = $this->account('household', 'Household', null);
        $employer = $this->account('employer', 'Employer', false, false);
        // A stale secondary profile does not change a household's verification status.
        DB::table('employers')->insert(['user_id' => $household, 'hirer_name' => 'Wrong hirer', 'isVerified' => true]);
        $this->assertSame([$student], $this->get('/admin/users?verification=verified')->viewData('users')->pluck('id')->all());
        $this->assertSame([$employer, $household], $this->get('/admin/users?verification=unverified')->viewData('users')->pluck('id')->all());
        $response = $this->get('/admin/users?role=employer&verification=unverified')->assertOk()->assertSee('Profile not completed')->assertViewHas('totalAccounts', 3);
        $this->assertSame([$employer], $response->viewData('users')->pluck('id')->all());
        $this->assertSame('09333333333', collect($this->get('/admin/users?role=household')->viewData('users')->first()->admin_details['fields'])->firstWhere('label', 'Contact number')['value']);
    }

    public function test_search_matches_name_email_business_and_school_with_literal_wildcards(): void
    {
        $student = $this->account('student', "Ana O'Neil");
        $employer = $this->account('employer', 'Business hirer');
        $other = $this->account('employer', 'Other hirer');
        DB::table('employers')->where('user_id', $other)->update(['employer_name' => '1000 Store']);
        foreach (["Ana O'Neil" => $student, 'Example school' => $student, '100%' => $employer, 'Business hirer' => $employer, DB::table('users')->where('id', $student)->value('email') => $student] as $search => $id) {
            $response = $this->get('/admin/users?search='.urlencode($search))->assertOk();
            $this->assertSame([$id], $response->viewData('users')->pluck('id')->all());
        }
    }

    public function test_details_are_escaped_handle_missing_profiles_and_link_to_document_review(): void
    {
        $student = $this->account('student', "Ana O'Neil <script>alert(1)</script>", true);
        $employer = $this->account('employer', 'Business hirer');
        $household = $this->account('household', 'Example household');
        $missing = $this->account('student', 'Missing student', false, false);
        $response = $this->get('/admin/users')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('private-test-password-hash', false)->assertSee('View documents');
        $studentDetails = $response->viewData('users')->firstWhere('id', $student)->admin_details;
        $this->assertSame('BSIT', collect($studentDetails['fields'])->firstWhere('label', 'Course')['value']);
        $this->assertSame('Example street, Pinamalayan, Oriental Mindoro', collect($studentDetails['fields'])->firstWhere('label', 'Address')['value']);
        $this->assertStringContainsString('role=student', $studentDetails['verificationUrl']);
        $this->assertStringContainsString('status=all', $studentDetails['verificationUrl']);
        $this->assertSame(false, $response->viewData('users')->firstWhere('id', $missing)->admin_details['hasProfile']);
        if ($path = getenv('DISKARTECH_USERS_PREVIEW_PATH')) file_put_contents($path, $response->getContent());
    }

    public function test_pagination_preserves_filters_and_global_totals(): void
    {
        for ($i = 0; $i < 12; $i++) $this->account();
        $this->account('household', 'Household', true);
        $response = $this->get('/admin/users?role=student&verification=unverified&search=Example')->assertOk()->assertViewHas('totalAccounts', 13);
        $this->assertSame(12, $response->viewData('users')->total()); $this->assertCount(10, $response->viewData('users'));
        $this->assertStringContainsString('verification=unverified', $response->viewData('users')->nextPageUrl());
        $this->assertCount(2, $this->get('/admin/users?role=student&verification=unverified&search=Example&page=2')->viewData('users'));
    }

    public function test_empty_state_invalid_filters_and_plain_text_normalization(): void
    {
        $this->get('/admin/users')->assertOk()->assertSee('No registered accounts yet');
        foreach (['role' => 'admin', 'verification' => 'active', 'search' => '<b>Bad</b>', 'page' => '-1'] as $field => $value) {
            $this->getJson('/admin/users?'.http_build_query([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->account();
        $this->get('/admin/users?search='.urlencode(" \x01Example "))->assertOk()->assertViewHas('search', 'Example');
        $this->get('/admin/users?search=Unknown')->assertOk()->assertSee('No matching accounts');
    }

    public function test_users_require_admin_authentication(): void
    {
        auth('admin')->logout();
        $this->get('/admin/users')->assertRedirect(route('login'));
    }
}
