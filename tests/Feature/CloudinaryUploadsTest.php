<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeVerificationDocument;
use App\Models\Employer;
use App\Models\Household;
use App\Models\Student;
use App\Models\User;
use App\Services\CloudinaryGateway;
use App\Services\UploadStorage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CloudinaryUploadsTest extends TestCase
{
    private const DOCUMENT = UploadStorage::DOCUMENT_PREFIX.'diskartech/student/coe/example.pdf';
    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ZkAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'uploads.driver' => 'cloudinary',
            'uploads.cloudinary' => ['cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret', 'folder' => 'diskartech'],
            'services.gemini.key' => 'test-ai-key', 'session.driver' => 'array']);
        Http::preventStrayRequests();
        Storage::fake('public');
        Queue::fake();
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('email')->unique(); $table->string('password'); $table->string('role');
            $table->boolean('isEmailVerified')->default(false); $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique();
            $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        foreach (['students', 'employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('user_id');
                foreach (['avatar', 'student_name', 'student_school_name', 'course', 'student_schedule', 'year_level', 'contact_number', 'age', 'gender', 'location', 'detailed_address', 'latitude', 'longitude', 'student_resume', 'school_id', 'coe', 'rejection_reason', 'school_id_ai_remarks', 'coe_ai_remarks', 'employer_name', 'hirer_name', 'business_type', 'valid_id_path', 'employer_certificate_path', 'cp_number', 'household_name', 'ai_remarks', 'cert_ai_remarks'] as $field) $table->string($field)->nullable();
                foreach (['school_id_ai_is_valid', 'coe_ai_is_valid', 'ai_is_valid', 'cert_ai_is_valid'] as $field) $table->boolean($field)->nullable();
                $table->boolean('isVerified')->default(false); $table->boolean('isSubscribed')->default(false); $table->timestamps();
            });
        }
    }

    private function login(string $role = 'student', int $id = 1): void
    {
        $user = new User; $user->id = $id; $user->role = $role;
        Sanctum::actingAs($user);
    }

    private function fakeUploads(int $count, array $expectedFolders = []): void
    {
        $this->mock(CloudinaryGateway::class, function ($mock) use ($count, $expectedFolders) {
            $mock->shouldReceive('upload')->times($count)->andReturnUsing(function ($file, $options) use (&$expectedFolders) {
                $this->assertFileExists($file);
                $this->assertFalse($options['overwrite']);
                if ($expectedFolders !== []) $this->assertSame(array_shift($expectedFolders), $options['asset_folder']);
                $this->assertFalse($options['use_asset_folder_as_public_id_prefix']);
                return [...$options, 'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/avatar.png'];
            });
        });
    }

    public static function registrationRoles(): array { return [['student', 3], ['employer', 2], ['household', 1]]; }

    #[DataProvider('registrationRoles')]
    public function test_registration_stores_documents_in_cloudinary_for_every_role(string $role, int $count): void
    {
        $this->fakeUploads($count, match ($role) {
            'student' => ['diskartech/student/school_id', 'diskartech/student/coe', 'diskartech/student/resume'],
            'employer' => ['diskartech/employers/certificates', 'diskartech/employers/validID'],
            default => ['diskartech/households/validIDs'],
        });
        $data = ['first_name' => 'Test', 'last_name' => 'Person', 'email' => $role.'@example.com', 'password' => 'Example1!', 'phone' => '09123456789',
            'school_name' => 'Example School', 'course' => 'BSIT', 'year_level' => '4th Year', 'business_name' => 'Example Business', 'business_type' => 'Retail'];
        $fields = match ($role) { 'student' => ['school_id_path', 'coe_path', 'resume_path'], 'employer' => ['certificate_path', 'valid_id_path'], default => ['valid_id_path'] };
        foreach ($fields as $field) $data[$field] = UploadedFile::fake()->createWithContent($field.'.pdf', self::PDF);
        $response = $this->postJson('/api/register/'.$role, $data)->assertCreated();
        $this->assertStringContainsString('/media/cloudinary/raw/authenticated/', $response->json('profile.'.($role === 'student' ? 'coe' : 'valid_id_path')));
        $table = match ($role) { 'student' => 'students', 'employer' => 'employers', default => 'households' };
        $stored = DB::table($table)->first();
        $this->assertStringStartsWith(UploadStorage::DOCUMENT_PREFIX, $role === 'student' ? $stored->coe : $stored->valid_id_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Queue::assertPushed(AnalyzeVerificationDocument::class, $role === 'student' ? 2 : $count);
    }

    public function test_reupload_resets_ai_and_returns_expiring_link_without_exposing_cloud_credentials(): void
    {
        Student::create(['user_id' => 1, 'coe' => 'old.pdf', 'coe_ai_is_valid' => false, 'coe_ai_remarks' => 'Invalid', 'isVerified' => true, 'rejection_reason' => 'Old rejection']);
        $this->login(); $this->fakeUploads(1);
        $response = $this->postJson('/api/student/upload-doc', ['document_type' => 'coe', 'file' => UploadedFile::fake()->createWithContent('document.pdf', self::PDF)])->assertOk();
        $student = Student::first();
        $this->assertStringStartsWith(UploadStorage::DOCUMENT_PREFIX, $student->coe);
        $this->assertNull($student->coe_ai_is_valid); $this->assertNull($student->rejection_reason); $this->assertFalse($student->isVerified);
        $url = $response->json('file_path');
        $this->assertStringContainsString('expires=', $url); $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('test-secret', $response->getContent());
        $this->assertStringNotContainsString('res.cloudinary.com', $url);
        $this->assertSame($url, $student->toArray()['coe']);
        $this->assertSame([], Storage::disk('public')->allFiles());
        Queue::assertPushed(AnalyzeVerificationDocument::class);
    }

    public static function hirerRoles(): array { return [['employer', Employer::class], ['household', Household::class]]; }

    #[DataProvider('hirerRoles')]
    public function test_hirer_verification_reupload_uses_cloud_and_resets_analysis(string $role, string $model): void
    {
        $model::create(['user_id' => 1, 'valid_id_path' => 'old.pdf', 'isVerified' => true, 'ai_is_valid' => false, 'ai_remarks' => 'Invalid']);
        $this->login($role); $this->fakeUploads($role === 'employer' ? 2 : 1);
        $data = ['type' => $role, 'valid_id_path' => UploadedFile::fake()->createWithContent('id.pdf', self::PDF)];
        if ($role === 'employer') $data['certificate_path'] = UploadedFile::fake()->createWithContent('permit.pdf', self::PDF);
        $this->postJson('/api/upload-verification-doc', $data)->assertOk();
        $profile = $model::first();
        $this->assertStringStartsWith(UploadStorage::DOCUMENT_PREFIX, $profile->valid_id_path);
        $this->assertNull($profile->ai_is_valid); $this->assertNull($profile->ai_remarks);
        $this->assertEquals(false, $profile->isVerified);
    }

    public static function avatarRoles(): array { return [['student', Student::class], ['employer', Employer::class], ['household', Household::class]]; }

    #[DataProvider('avatarRoles')]
    public function test_avatars_are_public_image_urls_for_all_roles(string $role, string $model): void
    {
        $model::create(['user_id' => 1]); $this->login($role);
        $this->fakeUploads(1, [match ($role) {
            'student' => 'diskartech/student/profile_pictures',
            'employer' => 'diskartech/employers/avatar',
            default => 'diskartech/households/avatar',
        }]);
        $image = UploadedFile::fake()->createWithContent('photo.png', base64_decode(self::PNG));
        $this->postJson($role === 'student' ? '/api/student/upload-doc' : '/api/employer/add-profile', $role === 'student' ? ['document_type' => 'profile_picture', 'file' => $image] : ['profile_picture' => $image])->assertOk();
        $this->assertSame('https://res.cloudinary.com/test-cloud/image/upload/avatar.png', $model::first()->avatar);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_provider_failure_does_not_replace_old_document_or_fall_back_to_ephemeral_storage(): void
    {
        Student::create(['user_id' => 1, 'coe' => 'original.pdf']); $this->login();
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldReceive('upload')->once()->andThrow(new \RuntimeException('provider secret details')));
        $response = $this->postJson('/api/student/upload-doc', ['document_type' => 'coe', 'file' => UploadedFile::fake()->createWithContent('file.pdf', self::PDF)])->assertStatus(503);
        $this->assertStringNotContainsString('provider secret details', $response->getContent());
        $this->assertSame('original.pdf', Student::first()->coe);
        $this->assertSame([], Storage::disk('public')->allFiles()); Queue::assertNothingPushed();
    }

    public function test_invalid_file_content_and_wrong_role_are_rejected_before_upload(): void
    {
        Student::create(['user_id' => 1]); $this->login();
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldNotReceive('upload'));
        $this->postJson('/api/student/upload-doc', ['document_type' => 'coe', 'file' => UploadedFile::fake()->createWithContent('fake.pdf', '<script>bad</script>')->mimeType('text/html')])->assertUnprocessable();
        $this->postJson('/api/upload-verification-doc', ['type' => 'employer', 'valid_id_path' => UploadedFile::fake()->createWithContent('id.pdf', self::PDF)])->assertForbidden();
        $this->postJson('/api/student/upload-doc', ['document_type' => 'profile_picture', 'file' => UploadedFile::fake()->createWithContent('photo.pdf', self::PDF)])->assertUnprocessable();
    }

    public function test_signed_document_delivery_and_expiration(): void
    {
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldReceive('documentUrl')->once()->andReturn('https://res.cloudinary.com/test-cloud/raw/authenticated/s--signed--/example.pdf'));
        Http::fake(['res.cloudinary.com/*' => Http::response(self::PDF, 200)]);
        $url = app(UploadStorage::class)->url(self::DOCUMENT);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertContent(self::PDF);
        $this->get(strtok($url, '?'))->assertForbidden();
        $this->get(str_replace('example.pdf', 'other.pdf', $url))->assertForbidden();
        $this->travel(61)->minutes(); $this->get($url)->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_local_legacy_documents_remain_available_and_cloud_references_cannot_use_public_storage_route(): void
    {
        Storage::disk('public')->put('old/example.pdf', self::PDF);
        $this->assertSame(self::PDF, app(UploadStorage::class)->read('old/example.pdf'));
        $this->assertStringContainsString('/storage/old/example.pdf', app(UploadStorage::class)->url('old/example.pdf'));
        $this->get('/storage/old/example.pdf')->assertOk();
        $this->get('/storage/'.self::DOCUMENT)->assertNotFound(); Http::assertNothingSent();
    }

    public function test_other_accounts_never_receive_verification_links_in_nested_profile_json(): void
    {
        $student = Student::create(['user_id' => 1, 'coe' => self::DOCUMENT, 'school_id' => self::DOCUMENT]);
        $this->login('student', 2);
        $this->assertArrayNotHasKey('coe', $student->toArray()); $this->assertArrayNotHasKey('school_id', $student->toArray());
        $this->assertSame(self::DOCUMENT, $student->coe);
    }

    public function test_sdk_builds_signed_authenticated_raw_url_without_network_access(): void
    {
        $url = app(CloudinaryGateway::class)->documentUrl('diskartech/test/file.pdf');
        $this->assertStringStartsWith('https://res.cloudinary.com/test-cloud/raw/authenticated/s--', $url);
        $this->assertStringContainsString('diskartech/test/file.pdf', $url);
        $this->assertStringNotContainsString('test-secret', $url); Http::assertNothingSent();
    }

    public function test_missing_cloud_credentials_and_oversized_files_never_use_local_storage(): void
    {
        Student::create(['user_id' => 1, 'coe' => 'original.pdf']); $this->login();
        config(['uploads.cloudinary.api_secret' => '']);
        $this->postJson('/api/student/upload-doc', ['document_type' => 'coe', 'file' => UploadedFile::fake()->createWithContent('file.pdf', self::PDF)])->assertStatus(503);
        $this->postJson('/api/student/upload-doc', ['document_type' => 'coe', 'file' => UploadedFile::fake()->create('huge.pdf', 5121)])->assertUnprocessable();
        $this->assertSame('original.pdf', Student::first()->coe);
        $this->assertSame([], Storage::disk('public')->allFiles()); Http::assertNothingSent();
    }

    public function test_hirer_gets_applied_student_resume_but_no_verification_links(): void
    {
        Schema::create('available_jobs', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); });
        Schema::create('job_applications', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('job_id'); $table->unsignedBigInteger('student_id'); });
        $student = Student::create(['user_id' => 1, 'student_resume' => self::DOCUMENT, 'coe' => self::DOCUMENT]);
        DB::table('available_jobs')->insert(['id' => 1, 'user_id' => 2]);
        DB::table('job_applications')->insert(['job_id' => 1, 'student_id' => $student->id]);
        $this->login('employer', 2);
        $this->assertStringContainsString('/media/cloudinary/', $student->toArray()['student_resume']);
        $this->assertArrayNotHasKey('coe', $student->toArray());
        $this->login('household', 3);
        $this->assertArrayNotHasKey('student_resume', $student->toArray());
    }

    public function test_signed_paths_cannot_traverse_or_request_an_external_url(): void
    {
        foreach (['../file.pdf', 'a/../../file.pdf', 'https://example.com/file.pdf', 'a\\file.pdf', '%2e%2e/file.pdf'] as $path) {
            try {
                app(UploadStorage::class)->read(UploadStorage::DOCUMENT_PREFIX.$path);
                $this->fail('Unsafe document path was accepted.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
        Http::assertNothingSent();
    }

    public function test_ai_reads_cloud_document_and_skips_stale_jobs(): void
    {
        $student = Student::create(['user_id' => 1, 'coe' => self::DOCUMENT]);
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldReceive('documentUrl')->once()->andReturn('https://res.cloudinary.com/test-cloud/document'));
        Http::fake(['res.cloudinary.com/*' => Http::response(self::PDF), 'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"is_valid":true,"remarks":"Valid enrollment document"}']]]]]])]);
        (new AnalyzeVerificationDocument($student, self::DOCUMENT, 'application/pdf', 'coe'))->handle();
        $this->assertEquals(true, $student->fresh()->coe_ai_is_valid);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage') && $request['contents'][0]['parts'][1]['inline_data']['data'] === base64_encode(self::PDF));
        $student->update(['coe' => 'replacement.pdf', 'coe_ai_is_valid' => null]);
        (new AnalyzeVerificationDocument($student, self::DOCUMENT, 'application/pdf', 'coe'))->handle();
        $this->assertNull($student->fresh()->coe_ai_is_valid); Http::assertSentCount(2);
    }

    public function test_ai_errors_leave_verification_analysis_pending(): void
    {
        $student = Student::create(['user_id' => 1, 'coe' => 'old.pdf']);
        Storage::disk('public')->put('old.pdf', self::PDF);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'not JSON']]]]]])]);
        (new AnalyzeVerificationDocument($student, 'old.pdf', 'application/pdf', 'coe'))->handle();
        $this->assertNull($student->fresh()->coe_ai_is_valid);
    }

    public function test_migration_previews_then_moves_existing_files_once_and_preserves_missing_references(): void
    {
        $student = Student::create(['user_id' => 1, 'coe' => 'old/coe.pdf', 'school_id' => 'missing.pdf']);
        Employer::create(['user_id' => 2, 'avatar' => 'old/avatar.png']);
        Storage::disk('public')->put('old/coe.pdf', self::PDF);
        Storage::disk('public')->put('old/avatar.png', base64_decode(self::PNG));
        $this->artisan('uploads:migrate-cloudinary')->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertSame('old/coe.pdf', $student->fresh()->coe);
        $this->fakeUploads(2);
        $this->artisan('uploads:migrate-cloudinary', ['--write' => true])->expectsOutputToContain('migrated: 2')->assertSuccessful();
        $this->assertStringStartsWith(UploadStorage::DOCUMENT_PREFIX, $student->fresh()->coe);
        $this->assertSame('missing.pdf', $student->fresh()->school_id);
        $this->assertStringStartsWith('https://res.cloudinary.com/', Employer::first()->avatar);
        Storage::disk('public')->assertExists(['old/coe.pdf', 'old/avatar.png']);
        Queue::assertPushed(AnalyzeVerificationDocument::class, 1);
        $this->artisan('uploads:migrate-cloudinary', ['--write' => true])->expectsOutputToContain('migrated: 0')->assertSuccessful();
    }
}
