<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
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
        $this->travelTo(Carbon::parse('2026-10-11 09:30:00', 'UTC'));
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
        (require database_path('migrations/2026_10_10_160000_add_interview_ended_at_to_job_applications.php'))->up();
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
        JWT::$timestamp = now()->timestamp;
        try { return JWT::decode($response->json('token'), new Key(self::SECRET, 'HS256')); }
        finally { JWT::$timestamp = null; }
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
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

    public function test_whitespace_is_normalized_without_exposing_credentials_or_diagnostics(): void
    {
        $this->login(1);
        config([
            'services.livekit.url' => " wss://test.livekit.cloud/\n",
            'services.livekit.api_key' => " API-local-test\r\n",
            'services.livekit.api_secret' => "\n".self::SECRET." \r\n",
        ]);
        $response = $this->postJson('/api/applications/27/interview-call/token', ['diagnostics' => true])
            ->assertOk()->assertJsonPath('server_url', 'wss://test.livekit.cloud');
        $this->assertArrayNotHasKey('diagnostics', $response->json());
        $this->assertSame('API-local-test', $this->decode($response)->iss);
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
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
        foreach ([['October 32, 2026', '05:30 PM'], ['October 11, 2026', '09:61 PM'], ['tomorrow', '05:30 PM']] as [$date, $time]) {
            DB::table('job_applications')->where('id', 27)->update(['interview_date' => $date, 'interview_time' => $time]);
            $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable();
        }
    }

    public function test_room_changes_on_reschedule_and_missing_config_fails_without_exposing_keys(): void
    {
        $this->login(1);
        $first = $this->decode($this->postJson('/api/applications/27/interview-call/token')->assertOk());
        DB::table('job_applications')->where('id', 27)->update(['interview_time' => '06:30 PM']);
        $this->travel(1)->hours();
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

    public function test_interview_times_handle_mobile_locale_formats_and_reject_invalid_clocks(): void
    {
        $this->login(1);
        $payload = ['status' => 'interview', 'interview_type' => 'online', 'interview_date' => 'October 12, 2026'];
        foreach ([
            '9:05 PM' => '09:05 PM', "09:05\u{202F}PM" => '09:05 PM',
            "\u{200E}09:05\u{00A0}pm\u{200F}" => '09:05 PM',
            '09:05PM' => '09:05 PM', '21:05' => '09:05 PM',
            '00:00:00' => '12:00 AM', '12:00' => '12:00 PM',
            '12:00 AM' => '12:00 AM',
        ] as $input => $expected) {
            $this->putJson('/api/employer/applications/27/status', $payload + ['interview_time' => $input])
                ->assertOk()->assertJsonPath('application.interview_time', $expected);
            $this->assertDatabaseHas('job_applications', ['id' => 27, 'interview_time' => $expected]);
        }
        foreach (['25:00', '13:00 PM', '00:30 AM', '09:61 PM', '09:30:45 PM', 'tomorrow', '09:30 AM extra', ['09:30 AM']] as $input) {
            $this->putJson('/api/employer/applications/27/status', $payload + ['interview_time' => $input])
                ->assertUnprocessable()->assertJsonValidationErrors('interview_time');
            $this->assertDatabaseHas('job_applications', ['id' => 27, 'interview_time' => '12:00 AM']);
        }
    }

    public function test_join_uses_philippine_schedule_and_blocks_before_start_and_after_end(): void
    {
        $this->login(2);
        $this->travelTo(Carbon::parse('2026-10-11 09:29:59', 'UTC'));
        $this->getJson('/api/applications/27/interview-call')->assertOk()
            ->assertJsonPath('interview_call.starts_at', '2026-10-11T17:30:00+08:00')
            ->assertJsonPath('interview_call.can_join', false)->assertJsonPath('interview_call.can_end', false);
        $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable()->assertJsonMissing(['token']);
        $this->travel(1)->seconds();
        $this->postJson('/api/applications/27/interview-call/token')->assertOk();
        $this->getJson('/api/applications/27/interview-call')->assertOk()->assertJsonPath('interview_call.can_join', true);
        DB::table('job_applications')->where('id', 27)->update(['interview_ended_at' => now()]);
        $this->getJson('/api/applications/27/interview-call')->assertOk()->assertJsonPath('interview_call.can_join', false);
        $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable()->assertJsonMissing(['token']);
        $this->login(1, 'household');
        $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable();
    }

    public function test_only_owner_can_end_and_livekit_receives_server_only_room_management_token(): void
    {
        Http::fake(['https://test.livekit.cloud/twirp/livekit.RoomService/DeleteRoom' => Http::response([], 200)]);
        $this->login(2);
        $room = $this->getJson('/api/applications/27/interview-call')->json('interview_call.room_name');
        foreach ([[2, null], [3, null], [4, 'employer'], [4, 'household'], [1, 'admin']] as [$id, $role]) {
            $this->login($id, $role);
            $this->postJson('/api/applications/27/interview-call/end', ['room_name' => $room])->assertForbidden();
        }
        Http::assertNothingSent();
        $this->login(1, 'household');
        $this->getJson('/api/applications/27/interview-call')->assertOk()->assertJsonPath('interview_call.can_end', true);
        $this->postJson('/api/applications/27/interview-call/end', ['room_name' => $room])->assertOk()
            ->assertJsonPath('room_closed', true)->assertJsonPath('interview_call.can_join', false);
        Http::assertSent(function ($request) use ($room) {
            JWT::$timestamp = now()->timestamp;
            try { $claims = JWT::decode(substr($request->header('Authorization')[0], 7), new Key(self::SECRET, 'HS256')); }
            finally { JWT::$timestamp = null; }
            return $request['room'] === $room && $claims->video->roomCreate === true && !isset($claims->video->roomJoin);
        });
        $endedAt = DB::table('job_applications')->where('id', 27)->value('interview_ended_at');
        $this->travel(1)->minutes();
        $this->postJson('/api/applications/27/interview-call/end', ['room_name' => $room])->assertOk();
        $this->assertSame($endedAt, DB::table('job_applications')->where('id', 27)->value('interview_ended_at'));
        $this->assertDatabaseHas('job_applications', ['id' => 27, 'status' => 'interview']);
        $this->login(2);
        $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable();
    }

    public function test_end_before_schedule_or_for_stale_room_is_rejected_and_reschedule_reopens(): void
    {
        $this->login(1);
        $room = $this->getJson('/api/applications/27/interview-call')->json('interview_call.room_name');
        $this->travel(-1)->seconds();
        $this->postJson('/api/applications/27/interview-call/end', ['room_name' => $room])->assertUnprocessable();
        $this->travel(1)->seconds();
        $this->postJson('/api/applications/27/interview-call/end', ['room_name' => 'stale-room'])->assertStatus(409);
        $this->assertDatabaseHas('job_applications', ['id' => 27, 'interview_ended_at' => null]);
        DB::table('job_applications')->where('id', 27)->update(['interview_ended_at' => now()]);
        $payload = ['status' => 'interview', 'interview_type' => 'online', 'interview_date' => 'October 11, 2026', 'interview_time' => '05:30 PM'];
        $this->putJson('/api/employer/applications/27/status', $payload)->assertOk();
        $this->assertNotNull(DB::table('job_applications')->where('id', 27)->value('interview_ended_at'));
        $payload['interview_time'] = '06:30 PM';
        $this->putJson('/api/employer/applications/27/status', $payload)->assertOk();
        $this->assertDatabaseHas('job_applications', ['id' => 27, 'interview_ended_at' => null]);
        $this->getJson('/api/applications/27/interview-call')->assertOk()->assertJsonPath('interview_call.can_join', false);
        $this->postJson('/api/applications/27/interview-call/end', ['room_name' => $room])->assertStatus(409);
        $this->travel(1)->hours();
        $this->postJson('/api/applications/27/interview-call/token')->assertOk();
    }

    public function test_provider_outage_does_not_reopen_interview_and_status_is_permission_checked(): void
    {
        Http::fake(['https://test.livekit.cloud/twirp/livekit.RoomService/DeleteRoom' => Http::response([], 503)]);
        $this->login(1);
        $room = $this->getJson('/api/applications/27/interview-call')->json('interview_call.room_name');
        $this->postJson('/api/applications/27/interview-call/end', ['room_name' => $room])->assertOk()
            ->assertJsonPath('room_closed', false)->assertJsonPath('interview_call.can_join', false);
        $this->login(2);
        $this->getJson('/api/applications/27/interview-call')->assertOk()->assertJsonPath('interview_call.can_join', false);
        $this->postJson('/api/applications/27/interview-call/token')->assertUnprocessable();
        $this->login(3);
        $this->getJson('/api/applications/27/interview-call')->assertForbidden();
        $this->getJson('/api/applications/999/interview-call')->assertNotFound();
    }
}
