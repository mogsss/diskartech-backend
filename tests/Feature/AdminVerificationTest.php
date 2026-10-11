<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Jobs\AnalyzeVerificationDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleAdminSession::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('email'); $table->string('role'); $table->timestamps();
        });
        foreach (['students', 'employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id(); $table->unsignedBigInteger('user_id'); $table->boolean('isVerified')->nullable()->default(false);
                $table->string('rejection_reason')->nullable(); $table->timestamps();
                foreach (match ($name) {
                    'students' => ['student_name', 'student_school_name', 'school_id', 'coe', 'student_resume', 'school_id_ai_remarks', 'coe_ai_remarks'],
                    'employers' => ['employer_name', 'hirer_name', 'valid_id_path', 'employer_certificate_path', 'ai_remarks', 'cert_ai_remarks'],
                    'households' => ['household_name', 'valid_id_path', 'ai_remarks'],
                } as $field) $table->string($field)->nullable();
                foreach (match ($name) {
                    'students' => ['school_id_ai_is_valid', 'coe_ai_is_valid'],
                    'employers' => ['ai_is_valid', 'cert_ai_is_valid'],
                    'households' => ['ai_is_valid'],
                } as $field) $table->boolean($field)->nullable();
            });
        }
        $admin = new Admin; $admin->id = 1; $admin->name = 'Test Administrator';
        $this->actingAs($admin, 'admin');
    }

    private function account(string $role, array $profile = [], bool $withProfile = true): int
    {
        $id = DB::table('users')->insertGetId([
            'email' => 'account-'.uniqid().'@example.com', 'role' => $role,
            'created_at' => '2026-10-11 01:00:00', 'updated_at' => '2026-10-11 01:00:00',
        ]);
        $data = match ($role) {
            'student' => ['student_name' => 'Student '.$id, 'student_school_name' => 'Example School', 'school_id' => 'school.jpg', 'coe' => 'coe.pdf', 'student_resume' => 'resume.pdf'],
            'employer' => ['employer_name' => 'Example Business', 'hirer_name' => 'Hirer '.$id, 'valid_id_path' => 'valid-id.jpg', 'employer_certificate_path' => 'permit.pdf'],
            'household' => ['household_name' => 'Household '.$id, 'valid_id_path' => 'valid-id.jpg'],
            default => null,
        };
        if ($withProfile && $data !== null) DB::table($role.'s')->insert(array_replace([
            'user_id' => $id, 'created_at' => '2026-10-11 01:00:00', 'updated_at' => '2026-10-11 01:00:00',
        ], $data, $profile));
        return $id;
    }

    public function test_status_counts_and_filters_partition_all_three_roles_correctly(): void
    {
        $ids = ['pending' => [], 'verified' => [], 'rejected' => [], 'incomplete' => []];
        foreach (['student', 'employer', 'household'] as $role) {
            $missingField = $role === 'student' ? 'coe' : 'valid_id_path';
            $ids['pending'][] = $this->account($role);
            $ids['verified'][] = $this->account($role, ['isVerified' => true, 'rejection_reason' => 'Old reason']);
            $ids['rejected'][] = $this->account($role, ['rejection_reason' => 'Invalid document']);
            $ids['incomplete'][] = $this->account($role, [$missingField => null]);
        }
        $ids['incomplete'][] = $this->account('student', [], false);
        $this->account('admin');
        $response = $this->get('/admin/verification')->assertOk()
            ->assertViewHas('status', 'pending')->assertViewHas('totalAccounts', 13)
            ->assertViewHas('statusCounts', ['pending' => 3, 'verified' => 3, 'rejected' => 3, 'incomplete' => 4]);
        $this->assertSame(array_reverse($ids['pending']), $response->viewData('users')->pluck('id')->all());
        foreach ($ids as $status => $expected) {
            $response = $this->get('/admin/verification?status='.$status)->assertOk();
            $this->assertSame(array_reverse($expected), $response->viewData('users')->pluck('id')->all());
            foreach ($expected as $id) $response->assertSee('data-account-id="'.$id.'" data-verification-status="'.$status.'"', false);
        }
    }

    public function test_search_combines_role_and_status_and_treats_wildcards_literally(): void
    {
        $wanted = $this->account('employer', ['employer_name' => '100% Store', 'hirer_name' => "Sam O'Neil"]);
        $this->account('employer', ['employer_name' => '1000 Store']);
        $this->account('student', ['student_school_name' => '100% Store']);
        $this->account('employer', ['employer_name' => '100% Store', 'isVerified' => true]);
        $response = $this->get('/admin/verification?role=employer&status=pending&search=100%25')->assertOk();
        $this->assertSame([$wanted], $response->viewData('users')->pluck('id')->all());
        foreach (["Sam O'Neil", '100% Store', DB::table('users')->where('id', $wanted)->value('email')] as $term) {
            $response = $this->get('/admin/verification?role=employer&status=pending&search='.urlencode($term))->assertOk();
            $this->assertSame([$wanted], $response->viewData('users')->pluck('id')->all());
        }
    }

    public function test_pagination_keeps_filters_and_uses_stable_newest_order(): void
    {
        $ids = [];
        for ($i = 0; $i < 17; $i++) $ids[] = $this->account('household');
        $response = $this->get('/admin/verification?role=household&status=pending&search=Household')->assertOk();
        $this->assertSame(17, $response->viewData('users')->total());
        $this->assertSame(array_slice(array_reverse($ids), 0, 15), $response->viewData('users')->pluck('id')->all());
        $this->assertStringContainsString('role=household', $response->viewData('users')->nextPageUrl());
        $response = $this->get('/admin/verification?role=household&status=pending&search=Household&page=2')->assertOk();
        $this->assertSame(array_slice(array_reverse($ids), 15), $response->viewData('users')->pluck('id')->all());
    }

    public function test_review_payload_escapes_names_and_handles_boolean_rejection_and_ai_results(): void
    {
        $student = $this->account('student', [
            'student_name' => "Ana O'Neil <script>alert(1)</script>", 'rejection_reason' => 'Unreadable ID',
            'school_id_ai_is_valid' => false, 'coe_ai_is_valid' => true,
        ]);
        $this->account('employer', ['ai_is_valid' => null, 'cert_ai_is_valid' => false]);
        $this->account('household', ['isVerified' => true]);
        $this->account('student', ['coe' => null]);
        $response = $this->get('/admin/verification?status=all')->assertOk()
            ->assertSee('data-account-id="'.$student.'" data-verification-status="rejected"', false)
            ->assertSee('Warning / Invalid Certificate')->assertSee('COR/COE')->assertSee('rejectionReason')
            ->assertSee('aria-modal="true"', false)->assertSee('showReview(')->assertDontSee('<script>alert(1)</script>', false);
        if ($path = getenv('DISKARTECH_VERIFICATION_PREVIEW_PATH')) file_put_contents($path, $response->getContent());
    }

    public function test_empty_queue_and_filter_validation(): void
    {
        $this->get('/admin/verification')->assertOk()->assertSee('No accounts waiting for review');
        foreach (['role' => 'admin', 'status' => 'unknown', 'search' => '<script>bad</script>'] as $key => $value) {
            $this->getJson('/admin/verification?'.http_build_query([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_approval_requires_complete_documents_for_every_role(): void
    {
        foreach (['student' => 'student_resume', 'employer' => 'employer_certificate_path', 'household' => 'valid_id_path'] as $role => $field) {
            $id = $this->account($role, [$field => null]);
            $this->from('/admin/verification')->post('/admin/verification/approve/'.$id, ['role' => $role])
                ->assertRedirect('/admin/verification')->assertSessionHas('error');
            $this->assertFalse((bool) DB::table($role.'s')->where('user_id', $id)->value('isVerified'));
        }
    }

    public function test_incomplete_accounts_cannot_be_rejected_for_any_role(): void
    {
        foreach (['student' => 'coe', 'employer' => 'employer_certificate_path', 'household' => 'valid_id_path'] as $role => $field) {
            $id = $this->account($role, [$field => null]);
            $this->from('/admin/verification')->post('/admin/verification/reject/'.$id, ['role' => $role, 'rejection_reason' => 'Invalid document'])
                ->assertRedirect('/admin/verification')->assertSessionHas('error');
            $this->assertNull(DB::table($role.'s')->where('user_id', $id)->value('rejection_reason'));
        }
    }

    public function test_complete_accounts_can_be_approved_and_old_rejection_is_cleared(): void
    {
        foreach (['student', 'employer', 'household'] as $role) {
            $id = $this->account($role, ['rejection_reason' => 'Old document issue']);
            $this->from('/admin/verification')->post('/admin/verification/approve/'.$id, ['role' => $role])
                ->assertRedirect('/admin/verification')->assertSessionHas('success');
            $this->assertTrue((bool) DB::table($role.'s')->where('user_id', $id)->value('isVerified'));
            $this->assertNull(DB::table($role.'s')->where('user_id', $id)->value('rejection_reason'));
        }
    }

    public function test_role_tampering_and_missing_profiles_cannot_approve(): void
    {
        $id = $this->account('student');
        $this->postJson('/admin/verification/approve/'.$id, ['role' => 'household'])->assertUnprocessable();
        $id = $this->account('household', [], false);
        $this->post('/admin/verification/approve/'.$id, ['role' => 'household'])->assertSessionHas('error');
    }

    public function test_rejection_validates_and_normalizes_plain_text(): void
    {
        $id = $this->account('student');
        foreach (['   ', '<b>Invalid ID</b>', '&lt;script&gt;bad&lt;/script&gt;', str_repeat('a', 256)] as $reason) {
            $this->postJson('/admin/verification/reject/'.$id, ['role' => 'student', 'rejection_reason' => $reason])
                ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        }
        $this->from('/admin/verification')->post('/admin/verification/reject/'.$id, [
            'role' => 'student', 'rejection_reason' => " \x00Please upload a clearer ID.\r\nThank you. ",
        ])->assertRedirect('/admin/verification')->assertSessionHas('success');
        $this->assertSame("Please upload a clearer ID.\nThank you.", DB::table('students')->where('user_id', $id)->value('rejection_reason'));
        $this->assertFalse((bool) DB::table('students')->where('user_id', $id)->value('isVerified'));
    }

    public function test_verified_accounts_can_be_rejected_with_a_reason_for_every_role(): void
    {
        foreach (['student', 'employer', 'household'] as $role) {
            $id = $this->account($role, ['isVerified' => true]);
            $this->from('/admin/verification?status=verified')->post('/admin/verification/reject/'.$id, [
                'role' => $role, 'rejection_reason' => "  Please replace the unreadable document.\r\nThank you.  ",
            ])->assertRedirect('/admin/verification?status=verified')->assertSessionHas('success');
            $this->assertFalse((bool) DB::table($role.'s')->where('user_id', $id)->value('isVerified'));
            $this->assertSame("Please replace the unreadable document.\nThank you.", DB::table($role.'s')->where('user_id', $id)->value('rejection_reason'));
            $this->get('/admin/verification?status=rejected&role='.$role)->assertOk()
                ->assertSee('data-account-id="'.$id.'" data-verification-status="rejected"', false);
            $this->assertSame(0, $this->get('/admin/verification?status=verified&role='.$role)->viewData('users')->total());
        }
    }

    public function test_previously_verified_accounts_with_missing_documents_can_also_be_rejected(): void
    {
        foreach (['student' => 'coe', 'employer' => 'employer_certificate_path', 'household' => 'valid_id_path'] as $role => $field) {
            $id = $this->account($role, ['isVerified' => true, $field => null]);
            $this->post('/admin/verification/reject/'.$id, ['role' => $role, 'rejection_reason' => 'Please re-upload the missing document.'])
                ->assertSessionHas('success');
            $this->assertFalse((bool) DB::table($role.'s')->where('user_id', $id)->value('isVerified'));
            $this->assertSame('Please re-upload the missing document.', DB::table($role.'s')->where('user_id', $id)->value('rejection_reason'));
        }
    }

    public function test_rejecting_verified_accounts_requires_a_valid_reason_and_matching_role(): void
    {
        $id = $this->account('employer', ['isVerified' => true]);
        foreach (['   ', '<b>Invalid ID</b>', '&lt;script&gt;bad&lt;/script&gt;', str_repeat('a', 256)] as $reason) {
            $this->postJson('/admin/verification/reject/'.$id, ['role' => 'employer', 'rejection_reason' => $reason])
                ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        }
        $this->postJson('/admin/verification/reject/'.$id, ['role' => 'student', 'rejection_reason' => 'Wrong account role'])
            ->assertUnprocessable();
        $this->assertTrue((bool) DB::table('employers')->where('user_id', $id)->value('isVerified'));
        $this->assertNull(DB::table('employers')->where('user_id', $id)->value('rejection_reason'));
    }

    public function test_admin_authentication_is_required(): void
    {
        auth('admin')->logout();
        $this->get('/admin/verification')->assertRedirect(route('login'));
        $this->post('/admin/verification/approve/1', ['role' => 'student'])->assertRedirect(route('login'));
        $this->post('/admin/verification/reject/1', ['role' => 'student', 'rejection_reason' => 'Invalid document'])->assertRedirect(route('login'));
        $this->post('/admin/verification/retry-analysis/1', ['role' => 'student', 'document' => 'coe'])->assertRedirect(route('login'));
    }

    public function test_admin_can_retry_pending_documents_for_every_role_without_duplicate_jobs(): void
    {
        Queue::fake();
        config(['queue.default' => 'database', 'services.gemini.key' => 'test-ai-key']);
        foreach (['student' => ['school_id', 'coe'], 'employer' => ['valid_id', 'certificate'], 'household' => ['valid_id']] as $role => $types) {
            $id = $this->account($role, ['isVerified' => true]);
            foreach ($types as $type) {
                $this->post('/admin/verification/retry-analysis/'.$id, ['role' => $role, 'document' => $type])
                    ->assertRedirect()->assertSessionHas('success');
            }
            $this->post('/admin/verification/retry-analysis/'.$id, ['role' => $role, 'document' => $types[0]])->assertSessionHas('success');
            $this->assertTrue((bool) DB::table($role.'s')->where('user_id', $id)->value('isVerified'));
        }
        Queue::assertPushed(AnalyzeVerificationDocument::class, 5);
    }

    public function test_analysis_retry_rejects_wrong_roles_document_types_and_completed_results(): void
    {
        Queue::fake();
        config(['queue.default' => 'database', 'services.gemini.key' => 'test-ai-key']);
        $id = $this->account('household', ['ai_is_valid' => false, 'ai_remarks' => 'Existing result']);
        foreach ([['role' => 'employer', 'document' => 'valid_id'], ['role' => 'household', 'document' => 'certificate'], ['role' => 'household', 'document' => '<script>valid_id</script>']] as $data) {
            $this->postJson('/admin/verification/retry-analysis/'.$id, $data)->assertUnprocessable();
        }
        $this->post('/admin/verification/retry-analysis/'.$id, ['role' => 'household', 'document' => 'valid_id'])
            ->assertSessionHas('success', 'This document already has an AI result.');
        $this->assertEquals(false, DB::table('households')->where('user_id', $id)->value('ai_is_valid'));
        $this->assertSame('Existing result', DB::table('households')->where('user_id', $id)->value('ai_remarks'));
        $id = $this->account('student', ['coe' => null]);
        $this->post('/admin/verification/retry-analysis/'.$id, ['role' => 'student', 'document' => 'coe'])->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_analysis_retry_requires_ai_configuration_and_a_background_queue(): void
    {
        Queue::fake();
        $id = $this->account('employer');
        config(['services.gemini.key' => null, 'queue.default' => 'database']);
        $this->post('/admin/verification/retry-analysis/'.$id, ['role' => 'employer', 'document' => 'valid_id'])->assertSessionHas('error');
        config(['services.gemini.key' => 'test-ai-key', 'queue.default' => 'sync']);
        $this->post('/admin/verification/retry-analysis/'.$id, ['role' => 'employer', 'document' => 'valid_id'])->assertSessionHas('error');
        Queue::assertNothingPushed();
    }
}
