<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\Household;
use App\Models\Student;
use App\Models\User;
use App\Services\CloudinaryGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileUploadRateLimitTest extends TestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ZkAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array',
            'uploads.driver' => 'cloudinary', 'uploads.cloudinary' => [
                'cloud_name' => 'test-cloud', 'api_key' => 'test-key', 'api_secret' => 'test-secret', 'folder' => 'diskartech',
            ]]);
        Cache::flush(); Http::preventStrayRequests(); Storage::fake('public'); Queue::fake();
        $this->travelTo(now()->startOfSecond());
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('email')->unique(); $table->string('password'); $table->string('role');
            $table->boolean('isEmailVerified')->default(true); $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique();
            $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        foreach (['students', 'employers', 'households'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('user_id');
                foreach (['avatar', 'student_name', 'student_resume', 'school_id', 'coe', 'valid_id_path',
                    'employer_certificate_path', 'rejection_reason', 'school_id_ai_remarks', 'coe_ai_remarks',
                    'ai_remarks', 'cert_ai_remarks'] as $field) $table->string($field)->nullable();
                foreach (['school_id_ai_is_valid', 'coe_ai_is_valid', 'ai_is_valid', 'cert_ai_is_valid'] as $field) $table->boolean($field)->nullable();
                $table->boolean('isVerified')->default(false); $table->timestamps();
            });
        }
    }

    private function account(string $role = 'student'): User
    {
        $user = User::create(['email' => $role.'-'.User::count().'@example.com', 'password' => 'Test-password1!', 'role' => $role]);
        $model = match ($role) { 'student' => Student::class, 'employer' => Employer::class, default => Household::class };
        $model::create(['user_id' => $user->id, 'avatar' => 'original.png']);
        return $user;
    }

    private function upload(User $user, string $type)
    {
        $avatar = $type === 'avatar';
        $file = UploadedFile::fake()->createWithContent($avatar ? 'photo.png' : 'document.pdf', $avatar ? base64_decode(self::PNG) : self::PDF);
        if ($user->role === 'student') {
            return $this->postJson('/api/student/upload-doc', ['document_type' => $avatar ? 'profile_picture' : $type, 'file' => $file]);
        }
        return $avatar
            ? $this->postJson('/api/employer/add-profile', ['profile_picture' => $file])
            : $this->postJson('/api/upload-verification-doc', ['type' => $user->role, $type => $file]);
    }

    public static function roles(): array { return [['student'], ['employer'], ['household']]; }

    #[DataProvider('roles')]
    public function test_avatar_and_documents_share_one_limit_and_recover_without_changing_blocked_files(string $role): void
    {
        $user = $this->account($role); Sanctum::actingAs($user);
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldReceive('upload')->times(6)
            ->andReturnUsing(fn ($file, $options) => [...$options, 'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/avatar.png']));
        $types = match ($role) {
            'student' => ['avatar', 'school_id', 'coe', 'student_resume', 'avatar'],
            'employer' => ['avatar', 'valid_id_path', 'certificate_path', 'avatar', 'valid_id_path'],
            default => ['avatar', 'valid_id_path', 'avatar', 'valid_id_path', 'avatar'],
        };
        foreach ($types as $type) $this->upload($user, $type)->assertOk();
        $table = match ($role) { 'student' => 'students', 'employer' => 'employers', default => 'households' };
        $snapshot = (array) DB::table($table)->first();
        Queue::fake();
        $nextType = $role === 'student' ? 'school_id' : 'valid_id_path';
        $this->upload($user, $nextType)->assertStatus(429)->assertJsonPath('code', 'upload_rate_limited')
            ->assertJsonPath('retry_after', 60)->assertHeader('Retry-After', '60')
            ->assertHeader('X-RateLimit-Limit', '5')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($snapshot, (array) DB::table($table)->first());
        Queue::assertNothingPushed(); $this->assertSame([], Storage::disk('public')->allFiles());
        $this->travel(20)->seconds();
        $this->upload($user, 'avatar')->assertStatus(429)->assertJsonPath('retry_after', 40);
        $this->travel(41)->seconds();
        $this->upload($user, 'avatar')->assertOk();
    }

    public function test_invalid_uploads_count_and_routes_or_submitted_ids_cannot_reset_the_counter(): void
    {
        Sanctum::actingAs($this->account());
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldNotReceive('upload'));
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/student/upload-doc', ['document_type' => 'coe'])->assertUnprocessable();
        foreach (['/api/student/upload-doc', '/api/employer/add-profile', '/api/upload-verification-doc'] as $route) {
            $this->postJson($route, ['user_id' => 999, 'document_type' => 'profile_picture', 'type' => 'household'])
                ->assertStatus(429)->assertJsonPath('code', 'upload_rate_limited');
        }
        Queue::assertNothingPushed();
    }

    public function test_accounts_on_the_same_ip_have_independent_limits(): void
    {
        $first = $this->account(); $second = $this->account();
        Sanctum::actingAs($first);
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/student/upload-doc')->assertUnprocessable();
        Sanctum::actingAs($second);
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/student/upload-doc')->assertUnprocessable();
        $this->postJson('/api/student/upload-doc')->assertStatus(429);
        Sanctum::actingAs($first);
        $this->postJson('/api/student/upload-doc')->assertStatus(429);
    }

    public function test_changing_ip_or_using_another_token_for_the_same_account_cannot_bypass_limit(): void
    {
        $user = $this->account();
        $first = $user->createToken('first-phone')->plainTextToken;
        $second = $user->createToken('second-phone')->plainTextToken;
        for ($i = 0; $i < 5; $i++) {
            $this->app['auth']->forgetGuards();
            $this->withToken($first)->postJson('/api/student/upload-doc')->assertUnprocessable();
        }
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99']);
        $this->withToken($second)->postJson('/api/student/upload-doc')->assertStatus(429);
    }

    public function test_provider_failures_count_without_replacing_the_avatar(): void
    {
        $user = $this->account(); Sanctum::actingAs($user);
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldReceive('upload')->times(5)
            ->andThrow(new \RuntimeException('provider secret details')));
        for ($i = 0; $i < 5; $i++) $this->upload($user, 'avatar')->assertStatus(503);
        $this->upload($user, 'avatar')->assertStatus(429);
        $this->assertSame('original.png', Student::first()->avatar);
        Queue::assertNothingPushed();
    }

    public function test_reading_profile_and_editing_text_do_not_consume_upload_allowance(): void
    {
        Sanctum::actingAs($this->account());
        for ($i = 0; $i < 8; $i++) {
            $this->getJson('/api/student/profile')->assertOk();
            $this->postJson('/api/user/update-profile', ['student_name' => 'Student Name'])->assertOk();
        }
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/student/upload-doc')->assertUnprocessable();
        $this->postJson('/api/student/upload-doc')->assertStatus(429);
    }

    public function test_unauthenticated_requests_still_require_login(): void
    {
        $this->mock(CloudinaryGateway::class, fn ($mock) => $mock->shouldNotReceive('upload'));
        foreach (['/api/student/upload-doc', '/api/employer/add-profile', '/api/upload-verification-doc'] as $route) {
            $this->postJson($route)->assertUnauthorized();
        }
        Queue::assertNothingPushed();
    }
}
