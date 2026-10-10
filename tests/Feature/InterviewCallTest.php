<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InterviewCallTest extends TestCase
{
    private const SECRET = 'local-test-signing-secret-only-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'services.livekit.url' => 'wss://test.livekit.cloud',
            'services.livekit.api_key' => 'API-local-test',
            'services.livekit.api_secret' => self::SECRET,
        ]);
        Http::preventStrayRequests();
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('role'); });
        Schema::create('students', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); });
        Schema::create('available_jobs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('title');
        });
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('job_id'); $table->unsignedBigInteger('student_id');
            $table->string('status');
            foreach (['interview_type', 'interview_date', 'interview_time', 'interview_location'] as $column) $table->string($column)->nullable();
            $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 1, 'role' => 'employer'], ['id' => 2, 'role' => 'student'],
            ['id' => 3, 'role' => 'student'], ['id' => 4, 'role' => 'employer'],
        ]);
        DB::table('students')->insert([['id' => 10, 'user_id' => 2], ['id' => 11, 'user_id' => 3]]);
        DB::table('available_jobs')->insert(['id' => 5, 'user_id' => 1, 'title' => 'Student helper']);
        DB::table('job_applications')->insert([
            'id' => 27, 'job_id' => 5, 'student_id' => 10, 'status' => 'interview',
            'interview_type' => 'online', 'interview_date' => 'October 11, 2026', 'interview_time' => '05:30 PM',
        ]);
    }

    private function login(int $id, ?string $role = null): void
    {
        $user = User::findOrFail($id);
        if ($role) $user->role = $role;
        Sanctum::actingAs($user);
    }

    private function decode($response): object
    {
        return JWT::decode($response->json('token'), new Key(self::SECRET, 'HS256'));
    }

    public function test_only_assigned_student_and_owner_receive_room_scoped_signed_tokens(): void
    {
        $this->login(1);
        $owner = $this->postJson('/api/applications/27/interview-call/token', ['room' => 'unrelated', 'identity' => 'attacker'])
            ->assertOk()->assertJsonPath('server_url', 'wss://test.livekit.cloud')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertArrayNotHasKey('diagnostics', $owner->json());
        $claims = $this->decode($owner);
        $this->assertSame('user-1', $claims->sub);
        $this->assertSame('API-local-test', $claims->iss);
        $this->assertSame(600, $claims->exp - $claims->iat);
        $this->assertStringStartsWith('interview-27-', $claims->video->room);
        $this->assertTrue($claims->video->roomJoin);
        $this->assertTrue($claims->video->canPublish);
        $this->assertTrue($claims->video->canSubscribe);
        $this->assertFalse($claims->video->canPublishData);
        $this->assertSame(['camera', 'microphone'], $claims->video->canPublishSources);
        $this->assertObjectNotHasProperty('roomAdmin', $claims->video);
        $this->assertStringNotContainsString(self::SECRET, $owner->getContent());
        $this->login(2);
        $student = $this->decode($this->postJson('/api/applications/27/interview-call/token')->assertOk());
        $this->assertSame($claims->video->room, $student->video->room);
        $this->assertSame('user-2', $student->sub);
        $this->login(1, 'household');
        $this->postJson('/api/applications/27/interview-call/token')->assertOk();
    }

    public function test_guest_unrelated_users_and_admin_cannot_join(): void
    {
        $this->postJson('/api/applications/27/interview-call/token')->assertUnauthorized();
        foreach ([[3, null], [4, null], [4, 'household'], [1, 'admin']] as [$id, $role]) {
            $this->login($id, $role);
            $this->postJson('/api/applications/27/interview-call/token')->assertForbidden();
        }
    }

    public function test_whitespace_is_normalized_and_diagnostics_compare_signing_configuration_without_credentials(): void
    {
        $this->login(1);
        config([
            'services.livekit.url' => " wss://test.livekit.cloud/\n",
            'services.livekit.api_key' => " API-local-test\r\n",
            'services.livekit.api_secret' => "\n".self::SECRET." \r\n",
        ]);
        $response = $this->postJson('/api/applications/27/interview-call/token', ['diagnostics' => true])
            ->assertOk()->assertJsonPath('server_url', 'wss://test.livekit.cloud')
            ->assertJsonPath('diagnostics.signing_key_fingerprint', substr(hash_hmac('sha256', 'diskartech-livekit-config-check-v1', self::SECRET), 0, 16))
            ->assertJsonPath('diagnostics.secret_length', strlen(self::SECRET))
            ->assertJsonPath('diagnostics.credential_whitespace_removed', true);
        $this->assertSame('API-local-test', $this->decode($response)->iss);
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());

        config(['services.livekit.api_secret' => 'different-test-secret-at-least-32-characters']);
        $changed = $this->postJson('/api/applications/27/interview-call/token', ['diagnostics' => true])->assertOk();
        $this->assertNotSame($response->json('diagnostics.signing_key_fingerprint'), $changed->json('diagnostics.signing_key_fingerprint'));
        $this->assertStringNotContainsString('different-test-secret-at-least-32-characters', $changed->getContent());
        $this->login(3);
        $this->postJson('/api/applications/27/interview-call/token', ['diagnostics' => true])->assertForbidden();
    }

    public function test_inactive_walk_in_and_unscheduled_interviews_cannot_join(): void
    {
        $this->login(2);
        foreach (['pending', 'viewed', 'accepted', 'hired', 'cancelled', 'rejected', 'terminated', 'completed'] as $status) {
            DB::table('job_applications')->where('id', 27)->update(['status' => $status]);
            $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable();
        }
        DB::table('job_applications')->where('id', 27)->update(['status' => 'interview', 'interview_type' => 'walk-in']);
        $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable();
        DB::table('job_applications')->where('id', 27)->update(['interview_type' => 'online', 'interview_time' => null]);
        $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable();
    }

    public function test_room_changes_on_reschedule_and_missing_config_fails_without_exposing_keys(): void
    {
        $this->login(1);
        $first = $this->decode($this->postJson('/api/applications/27/interview-call/token')->assertOk());
        DB::table('job_applications')->where('id', 27)->update(['interview_time' => '06:30 PM']);
        $second = $this->decode($this->postJson('/api/applications/27/interview-call/token')->assertOk());
        $this->assertNotSame($first->video->room, $second->video->room);
        config(['services.livekit.api_secret' => null]);
        $this->postJson('/api/applications/27/interview-call/token')->assertStatus(503)->assertJsonMissing(['token']);
        $this->postJson('/api/applications/999/interview-call/token')->assertNotFound();
    }

    public function test_online_scheduling_needs_only_date_and_time_and_preserves_walk_in_venue(): void
    {
        $this->login(1);
        $payload = ['status' => 'interview', 'interview_type' => 'online', 'interview_date' => 'October 12, 2026', 'interview_time' => '09:00 AM'];
        $this->putJson('/api/employer/applications/27/status', $payload)->assertOk()->assertJsonPath('application.interview_location', 'DiskarTech video call');
        $this->putJson('/api/employer/applications/27/status', array_diff_key($payload, ['interview_time' => 1]))->assertUnprocessable();
        $payload['interview_type'] = 'walk-in';
        $this->putJson('/api/employer/applications/27/status', $payload)->assertUnprocessable();
        $payload['interview_location'] = 'Office venue';
        $this->putJson('/api/employer/applications/27/status', $payload)->assertOk()->assertJsonPath('application.interview_location', 'Office venue');
        $this->login(4);
        $this->putJson('/api/employer/applications/27/status', $payload)->assertForbidden();
        $this->assertDatabaseHas('job_applications', ['id' => 27, 'interview_location' => 'Office venue']);
    }
}
