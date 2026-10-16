<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountDeletion;
use App\Services\CloudinaryGateway;
use App\Services\GmailOtpMailer;
use App\Services\GmailDeliveryException;
use App\Services\UploadStorage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    private string $code = '';
    private array $recipients = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array',
            'session.driver' => 'array', 'uploads.cloudinary.cloud_name' => 'fixture']);
        Http::preventStrayRequests();
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('registered_with_google')->default(false); $table->boolean('isEmailVerified')->default(true);
            $table->string('otp_code')->nullable(); $table->timestamp('otp_expires_at')->nullable();
        });
        (require database_path('migrations/2026_08_14_025124_create_personal_access_tokens_table.php'))->up();
        foreach (['students', 'employers', 'households'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id(); $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('student_name')->default('Person'); $table->string('student_school_name')->default('School');
                $table->string('employer_name')->default('Business'); $table->string('household_name')->default('Household');
                $table->string('hirer_name')->nullable(); $table->string('location')->default('Pinamalayan');
                foreach (['contact_number', 'detailed_address', 'school_id', 'coe', 'avatar', 'valid_id_path', 'expo_push_token', 'ai_remarks'] as $field) $table->string($field)->nullable();
                $table->decimal('latitude', 10, 7)->nullable(); $table->json('skillset')->nullable();
                $table->boolean('isVerified')->default(true); $table->boolean('isSubscribed')->default(false); $table->timestamps();
            });
        }
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->default('Job'); $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('status')->default('active'); $table->timestamps();
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id(); $table->foreignId('job_id')->constrained('available_jobs')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete(); $table->string('status');
            $table->timestamp('terminated_at')->nullable(); $table->text('termination_reason')->nullable();
            $table->timestamp('interview_ended_at')->nullable(); $table->string('certificate_status')->nullable(); $table->timestamps();
        });
        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('student_id'); $table->unsignedBigInteger('job_id');
        });
        foreach (['reports', 'reviews'] as $name) Schema::create($name, function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->text('description');
        });
        (require database_path('migrations/2026_10_13_120000_create_subscription_payments_table.php'))->up();
        (require database_path('migrations/2026_10_12_140000_create_mobile_password_resets_table.php'))->up();
        (require database_path('migrations/2026_10_16_120000_create_account_deletion_tables.php'))->up();
        $this->mock(GmailOtpMailer::class)->shouldReceive('sendAccountDeletionCode')->andReturnUsing(function ($email, $code) {
            $this->recipients[] = $email; $this->code = $code;
        });
    }

    private function account(string $role = 'student', bool $google = false): array
    {
        $user = User::create(['email' => bin2hex(random_bytes(8)).'@example.com', 'password' => 'Original-password1!', 'role' => $role, 'registered_with_google' => $google]);
        if (in_array($role, ['student', 'employer', 'household'])) {
            DB::table(['student' => 'students', 'employer' => 'employers', 'household' => 'households'][$role])->insert([
                'user_id' => $user->id, 'hirer_name' => 'Private Name', 'contact_number' => '09123456789',
                'latitude' => 13.0, 'detailed_address' => 'Private Address', 'skillset' => '["Cleaning"]',
                'expo_push_token' => 'push-secret', 'ai_remarks' => 'Private Document Detail',
            ]);
        }
        return [$user, $user->createToken('phone', ['*', $google ? 'auth:google' : 'auth:password'])->plainTextToken];
    }

    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($token);
    }

    private function send(string $token): void
    {
        $this->asToken($token)->postJson('/api/user/deletion-code')->assertOk()->assertJsonPath('expires_in', 300)->assertHeader('Cache-Control', 'no-store, private');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['code' => $this->code, 'confirmation' => 'DELETE', 'acknowledged' => true], $overrides);
    }

    public function test_deletion_requires_authentication_and_a_mobile_role(): void
    {
        $this->postJson('/api/user/deletion-code')->assertUnauthorized();
        $this->deleteJson('/api/user/account', $this->payload())->assertUnauthorized();
        [, $token] = $this->account('admin');
        $this->asToken($token)->postJson('/api/user/deletion-code')->assertForbidden();
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertForbidden();
    }

    public function test_all_mobile_roles_and_google_accounts_can_delete_only_themselves(): void
    {
        [$other] = $this->account();
        foreach (['student', 'employer', 'household'] as $role) {
            foreach ([false, true] as $google) {
                [$user, $token] = $this->account($role, $google);
                $user->createToken('second phone');
                $this->send($token);
                $this->assertSame($user->email, end($this->recipients));
                $challenge = DB::table('account_deletion_codes')->where('user_id', $user->id)->first();
                $this->assertNotSame($this->code, $challenge->code_hash);
                $this->asToken($token)->deleteJson('/api/user/account', $this->payload(['user_id' => $other->id]))
                    ->assertOk()->assertJsonPath('status', 'success');
                $this->assertNull(User::find($user->id));
                $this->assertSame('deleted', DB::table('users')->where('id', $user->id)->value('role'));
                $this->assertSame(0, $user->tokens()->count());
                $this->assertDatabaseMissing('account_deletion_codes', ['user_id' => $user->id]);
                $this->assertNotNull(User::find($other->id));
                $profile = DB::table(['student' => 'students', 'employer' => 'employers', 'household' => 'households'][$role])->where('user_id', $user->id)->first();
                $this->assertSame('Deleted account', $profile->hirer_name);
                $this->assertNull($profile->contact_number); $this->assertNull($profile->latitude);
                $this->assertNull($profile->detailed_address); $this->assertNull($profile->skillset);
                $this->assertNull($profile->expo_push_token); $this->assertNull($profile->ai_remarks);
                $this->assertFalse((bool) $profile->isVerified);
                $this->asToken($token)->getJson('/api/user')->assertUnauthorized();
            }
        }
    }

    public function test_confirmation_is_required_and_codes_are_bound_to_the_account_and_login_session(): void
    {
        [$user, $token] = $this->account();
        $this->send($token);
        foreach ([['confirmation' => 'delete'], ['acknowledged' => false], ['code' => '123']] as $invalid) {
            $this->asToken($token)->deleteJson('/api/user/account', $this->payload($invalid))->assertUnprocessable();
        }
        $second = $user->createToken('other phone')->plainTextToken;
        $this->asToken($second)->deleteJson('/api/user/account', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('code');
        [$other, $otherToken] = $this->account();
        $this->asToken($otherToken)->deleteJson('/api/user/account', $this->payload())->assertUnprocessable();
        $this->assertNotNull(User::find($other->id));
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertOk();
    }

    public function test_five_wrong_codes_lock_the_challenge_and_expired_codes_cannot_delete(): void
    {
        [$user, $token] = $this->account(); $this->send($token);
        $wrong = $this->code === '123456' ? '654321' : '123456';
        for ($i = 0; $i < 5; $i++) $this->asToken($token)->deleteJson('/api/user/account', $this->payload(['code' => $wrong]))->assertUnprocessable();
        $this->assertSame(5, (int) DB::table('account_deletion_codes')->where('user_id', $user->id)->value('attempts'));
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertUnprocessable();
        $this->travel(61)->seconds(); $this->send($token);
        $this->travel(5)->minutes();
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertUnprocessable();
        $this->assertNotNull(User::find($user->id));
    }

    public function test_deletion_code_is_still_valid_just_before_five_minutes(): void
    {
        [, $token] = $this->account();
        $this->send($token);
        $this->travel(299)->seconds();
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertOk();
    }

    public function test_resending_has_account_wide_cooldown_and_hourly_limit_and_rotates_the_code(): void
    {
        [$user, $token] = $this->account(); $this->send($token);
        $second = $user->createToken('second phone')->plainTextToken;
        $this->asToken($second)->postJson('/api/user/deletion-code', ['email' => 'attacker@example.com'])->assertStatus(429);
        $this->assertCount(1, $this->recipients);
        $this->travel(61)->seconds(); $this->send($second);
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertUnprocessable();
        $this->travel(61)->seconds(); $this->send($second);
        $this->travel(61)->seconds(); $this->asToken($second)->postJson('/api/user/deletion-code')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_mail_failure_never_provides_a_deletion_challenge_or_leaks_provider_details(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('stderr')->andReturnSelf();
        [$user, $token] = $this->account();
        $this->mock(GmailOtpMailer::class)->shouldReceive('sendAccountDeletionCode')->andThrow(new \RuntimeException('provider-secret'));
        $this->asToken($token)->postJson('/api/user/deletion-code')->assertStatus(503)->assertDontSee('provider-secret');
        $this->assertDatabaseMissing('account_deletion_codes', ['user_id' => $user->id]);
        $this->assertNotNull(User::find($user->id));
        Log::shouldHaveReceived('warning')->with('Account deletion email delivery could not complete.', [
            'stage' => 'send_email', 'exception_type' => \RuntimeException::class,
        ])->once();
    }

    public function test_real_deletion_mailer_creates_the_email_and_saves_a_usable_challenge(): void
    {
        [$user, $token] = $this->account('student', true);
        $this->app->instance(GmailOtpMailer::class, new GmailOtpMailer);
        config(['services.gmail.client_id' => 'fixture-client', 'services.gmail.client_secret' => 'fixture-secret',
            'services.gmail.refresh_token' => 'fixture-refresh', 'services.gmail.from_address' => 'sender@example.com']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fixture-access', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'fixture-message']),
        ]);
        $this->asToken($token)->postJson('/api/user/deletion-code')->assertOk()->assertDontSee('fixture-access');
        $sentCode = null;
        Http::assertSent(function ($request) use ($user, &$sentCode) {
            if (!str_contains($request->url(), 'messages/send')) return false;
            $mime = base64_decode(strtr($request['raw'], '-_', '+/'));
            preg_match('/verification code is: ([0-9]{6})/', $mime, $match);
            $sentCode = $match[1] ?? null;
            return $request->hasHeader('Authorization', 'Bearer fixture-access') && $sentCode
                && str_contains($mime, 'Subject: Confirm Account Deletion')
                && str_contains($mime, 'To: '.$user->email) && str_contains($mime, '5 minutes');
        });
        $this->assertSame(hash_hmac('sha256', $sentCode, config('app.key')), DB::table('account_deletion_codes')->where('user_id', $user->id)->value('code_hash'));
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload(['code' => $sentCode]))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_gmail_configuration_failure_has_a_safe_diagnostic(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('stderr')->andReturnSelf();
        [, $token] = $this->account();
        $this->app->instance(GmailOtpMailer::class, new GmailOtpMailer);
        config(['services.gmail.refresh_token' => null]);
        $this->asToken($token)->postJson('/api/user/deletion-code')->assertStatus(503);
        Log::shouldHaveReceived('warning')->with('Account deletion email delivery could not complete.', [
            'stage' => 'send_email', 'exception_type' => GmailDeliveryException::class,
            'reason' => 'Gmail OTP credentials are not configured.',
        ])->once();
        Http::assertNothingSent();
    }

    public function test_database_failure_is_distinguishable_from_email_failure_without_logging_sql(): void
    {
        Log::spy();
        Log::shouldReceive('channel')->with('stderr')->andReturnSelf();
        [, $token] = $this->account();
        Schema::drop('account_deletion_codes');
        $this->asToken($token)->postJson('/api/user/deletion-code')->assertStatus(503);
        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return $message === 'Account deletion email delivery could not complete.'
                && $context['stage'] === 'load_challenge' && isset($context['sqlstate'])
                && count($context) === 3;
        })->once();
        $this->assertCount(0, $this->recipients);
    }

    public function test_active_work_and_pro_do_not_block_deletion_and_shared_records_survive(): void
    {
        [$hirer, $token] = $this->account('employer'); [$student] = $this->account();
        $studentId = DB::table('students')->where('user_id', $student->id)->value('id');
        DB::table('employers')->where('user_id', $hirer->id)->update(['isSubscribed' => true, 'subscription_plan' => 'premium', 'subscription_expires_at' => now()->addYear()]);
        $jobId = DB::table('available_jobs')->insertGetId(['user_id' => $hirer->id, 'title' => 'Shared work']);
        DB::table('job_postings')->insert(['user_id' => $hirer->id]);
        foreach (['accepted', 'hired', 'pending', 'viewed', 'interview', 'completed', 'terminated', 'rejected'] as $status) {
            DB::table('job_applications')->insert(['job_id' => $jobId, 'student_id' => $studentId, 'status' => $status, 'certificate_status' => 'approved']);
        }
        foreach (['reports', 'reviews'] as $table) DB::table($table)->insert(['user_id' => $hirer->id, 'description' => 'Shared record']);
        $this->send($token); $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertOk();
        $this->assertSame('closed', DB::table('available_jobs')->where('id', $jobId)->value('status'));
        $this->assertSame('closed', DB::table('job_postings')->value('status'));
        $this->assertSame(3, DB::table('job_applications')->where('status', 'terminated')->count());
        $this->assertSame(3, DB::table('job_applications')->where('status', 'cancelled')->count());
        $this->assertSame(1, DB::table('job_applications')->where('status', 'completed')->count());
        $this->assertSame(8, DB::table('job_applications')->where('certificate_status', 'approved')->count());
        foreach (['reports', 'reviews'] as $table) $this->assertDatabaseHas($table, ['description' => 'Shared record']);
        $this->assertFalse((bool) DB::table('employers')->value('isSubscribed'));
        $this->assertNotNull(User::find($student->id));
    }

    public function test_deleted_accounts_cannot_login_or_be_restored_through_the_anonymous_email(): void
    {
        [$user, $token] = $this->account(); $email = $user->email;
        $this->send($token); $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertOk();
        $placeholder = DB::table('users')->where('id', $user->id)->first();
        $this->assertFalse(Hash::check('Original-password1!', $placeholder->password));
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $email, 'password' => 'Original-password1!'])->assertNotFound();
        $this->postJson('/api/google-login', ['email' => $email])->assertNotFound();
        $this->postJson('/api/google-login', ['email' => $placeholder->email])->assertNotFound();
    }

    public function test_failed_deletion_rolls_back_profile_changes_and_keeps_session_and_code(): void
    {
        [$user, $token] = $this->account(); $this->send($token);
        $this->mock(AccountDeletion::class)->shouldReceive('delete')->andReturnUsing(function ($user) {
            DB::table('students')->where('user_id', $user->id)->update(['contact_number' => null]);
            throw new \RuntimeException('transaction failure');
        });
        $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertStatus(500);
        $this->assertDatabaseHas('students', ['user_id' => $user->id, 'contact_number' => '09123456789']);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseHas('account_deletion_codes', ['user_id' => $user->id]);
    }

    public function test_profile_file_cleanup_is_durable_retried_and_preserves_external_avatars(): void
    {
        Storage::fake('public');
        [$user, $token] = $this->account();
        Storage::disk('public')->put('student/school_id/owned.pdf', 'private');
        DB::table('students')->where('user_id', $user->id)->update(['school_id' => 'student/school_id/owned.pdf', 'avatar' => 'https://example.com/avatar.png']);
        $this->send($token); $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertOk();
        $this->assertDatabaseCount('account_deletion_files', 2);
        $this->artisan('accounts:cleanup-files')->assertSuccessful();
        Storage::disk('public')->assertMissing('student/school_id/owned.pdf');
        $this->assertDatabaseCount('account_deletion_files', 0);

        $path = UploadStorage::DOCUMENT_PREFIX.'diskartech/student/coe/owned.pdf';
        DB::table('account_deletion_files')->insert(['user_id' => $user->id, 'path' => $path, 'created_at' => now(), 'updated_at' => now()]);
        $this->mock(CloudinaryGateway::class)->shouldReceive('delete')->once()->andThrow(new \RuntimeException('storage-secret'));
        $this->artisan('accounts:cleanup-files')->assertSuccessful();
        $this->assertDatabaseHas('account_deletion_files', ['path' => $path]);
        $this->mock(CloudinaryGateway::class)->shouldReceive('delete')->once()->with('diskartech/student/coe/owned.pdf', ['resource_type' => 'raw', 'type' => 'authenticated', 'invalidate' => true]);
        $this->artisan('accounts:cleanup-files')->assertSuccessful();
        $this->assertDatabaseCount('account_deletion_files', 0);
    }

    public function test_cleanup_removes_owned_cloud_images_but_keeps_files_referenced_by_another_profile(): void
    {
        [$user, $token] = $this->account(); [$other] = $this->account();
        $image = 'https://res.cloudinary.com/fixture/image/upload/v123/diskartech/student/avatar/owned.png';
        $shared = 'student/school_id/shared.pdf';
        DB::table('students')->where('user_id', $user->id)->update(['avatar' => $image, 'school_id' => $shared]);
        DB::table('students')->where('user_id', $other->id)->update(['school_id' => $shared]);
        Storage::fake('public'); Storage::disk('public')->put($shared, 'shared');
        $this->send($token); $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertOk();
        $this->mock(CloudinaryGateway::class)->shouldReceive('delete')->once()->with('diskartech/student/avatar/owned', ['resource_type' => 'image', 'type' => 'upload', 'invalidate' => true]);
        $this->artisan('accounts:cleanup-files')->assertSuccessful();
        Storage::disk('public')->assertExists($shared);
        $this->assertDatabaseCount('account_deletion_files', 0);
    }

    public function test_deletion_migration_is_idempotent_and_clears_saved_jobs_and_reset_challenges(): void
    {
        (require database_path('migrations/2026_10_16_120000_create_account_deletion_tables.php'))->up();
        [$user, $token] = $this->account(); [$employer] = $this->account('employer');
        $jobId = DB::table('available_jobs')->insertGetId(['user_id' => $employer->id]);
        $studentId = DB::table('students')->where('user_id', $user->id)->value('id');
        DB::table('saved_jobs')->insert(['student_id' => $studentId, 'job_id' => $jobId]);
        DB::table('mobile_password_resets')->insert(['email' => $user->email, 'user_id' => $user->id, 'expires_at' => now()->addMinutes(10), 'sent_at' => now()]);
        $this->send($token); $this->asToken($token)->deleteJson('/api/user/account', $this->payload())->assertOk();
        $this->assertDatabaseMissing('saved_jobs', ['student_id' => $studentId]);
        $this->assertDatabaseMissing('mobile_password_resets', ['user_id' => $user->id]);
        $this->assertDatabaseHas('available_jobs', ['id' => $jobId, 'status' => 'active']);
    }
}
