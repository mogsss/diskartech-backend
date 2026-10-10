<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InterviewCallController extends Controller
{
    private function application(Request $request, int $id, bool $lock = false): JobApplication
    {
        $query = JobApplication::with(['job', 'student']);
        if ($lock) $query->lockForUpdate();
        $application = $query->findOrFail($id);
        $user = $request->user();
        $isStudent = $user->role === 'student' && (int) $application->student?->user_id === (int) $user->id;
        $isOwner = in_array($user->role, ['employer', 'household'], true)
            && (int) $application->job?->user_id === (int) $user->id;
        abort_unless($isStudent || $isOwner, 403, 'You cannot join this interview.');
        return $application;
    }

    private function availability(JobApplication $application, Request $request): array
    {
        $startsAt = null;
        try {
            $dateParts = $application->interview_date ? date_parse($application->interview_date) : null;
            if ($dateParts && !$dateParts['error_count'] && !$dateParts['warning_count']
                && $dateParts['year'] && $dateParts['month'] && $dateParts['day']
                && preg_match('/^(0?[1-9]|1[0-2]):[0-5]\d [AP]M$/', (string) $application->interview_time)) {
                // Interview picker values are Philippine local time, never server UTC/device time.
                $time = CarbonImmutable::createFromFormat('!h:i A', $application->interview_time, 'Asia/Manila');
                $startsAt = CarbonImmutable::parse($application->interview_date, 'Asia/Manila')
                    ->startOfDay()->setTime($time->hour, $time->minute);
            }
        } catch (\Throwable) {
            // Incomplete/legacy malformed schedules remain unavailable.
        }
        $active = $application->status === 'interview' && $application->interview_type === 'online' && $startsAt;
        $ended = $application->interview_ended_at !== null;
        $available = (bool) ($active && !$ended && now()->greaterThanOrEqualTo($startsAt));
        return [
            'server_now' => now()->toIso8601String(),
            'starts_at' => $startsAt?->toIso8601String(),
            'ended_at' => $application->interview_ended_at?->toIso8601String(),
            'active' => (bool) $active, 'can_join' => $available,
            'can_end' => $available && in_array($request->user()->role, ['employer', 'household'], true),
            'room_name' => $this->roomName($application),
            'message' => $ended ? 'This interview has ended.' : (!$active
                ? 'This application does not have an active online interview.'
                : ($available ? 'Ready to join.' : 'Join becomes available at the scheduled date and time (Philippine time).')),
        ];
    }

    private function roomName(JobApplication $application): string
    {
        $schedule = hash('sha256', $application->interview_date.'|'.$application->interview_time);
        return 'interview-'.$application->id.'-'.substr($schedule, 0, 16);
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['status' => 'success', 'interview_call' => $this->availability($this->application($request, $id), $request)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function token(Request $request, int $id)
    {
        return DB::transaction(function () use ($request, $id) {
            // Serialize token issuance against End Interview so a closed interview cannot issue new tokens.
            $application = $this->application($request, $id, true);
            $availability = $this->availability($application, $request);
            if (!$availability['can_join']) {
                return response()->json(['status' => 'error', 'message' => $availability['message'], 'interview_call' => $availability], 422);
            }
            return $this->issueToken($application, $request, $availability);
        });
    }

    private function issueToken(JobApplication $application, Request $request, array $availability)
    {
        $user = $request->user();
        $isStudent = $user->role === 'student';
        $url = trim((string) config('services.livekit.url'));
        $key = trim((string) config('services.livekit.api_key'));
        $secret = trim((string) config('services.livekit.api_secret'));
        if (!preg_match('~^wss://[a-zA-Z0-9.-]+(?::\d+)?/?$~', $url) || !$key || strlen($secret) < 32) {
            return response()->json(['status' => 'error', 'message' => 'Video calls are not configured yet. Please contact support.'], 503);
        }

        // Rescheduling creates a separate room; no client-selected room or identity is accepted.
        $room = $this->roomName($application);
        $now = now()->timestamp;
        $token = JWT::encode([
            'iss' => $key, 'sub' => 'user-'.$user->id, 'iat' => $now, 'nbf' => $now - 5, 'exp' => $now + 600,
            'name' => $isStudent ? 'Student' : ($user->role === 'household' ? 'Household' : 'Employer'),
            'video' => [
                'roomJoin' => true, 'room' => $room, 'canPublish' => true,
                'canSubscribe' => true, 'canPublishData' => false,
                'canPublishSources' => ['camera', 'microphone'],
            ],
        ], $secret, 'HS256');

        return response()->json([
            'status' => 'success', 'server_url' => rtrim($url, '/'), 'token' => $token,
            'title' => $application->job?->title ?? 'Online interview',
            'interview_call' => $availability,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function end(Request $request, int $id)
    {
        $request->validate(['room_name' => 'required|string|max:100']);
        $application = DB::transaction(function () use ($request, $id) {
            $application = $this->application($request, $id, true);
            abort_unless(in_array($request->user()->role, ['employer', 'household'], true), 403, 'Only the employer or household can end this interview.');
            // Prevent an old call screen from ending a newly rescheduled interview.
            abort_unless(hash_equals($this->roomName($application), $request->input('room_name')), 409, 'The interview was rescheduled. Open its details again.');
            if (!$application->interview_ended_at) {
                $availability = $this->availability($application, $request);
                abort_unless($availability['can_end'], 422, $availability['message']);
                $application->interview_ended_at = now();
                $application->save();
            }
            return $application;
        });

        // Persist closure before contacting LiveKit. If the provider is unavailable,
        // availability polling still disconnects both app clients and blocks new joins.
        $roomClosed = false;
        $key = trim((string) config('services.livekit.api_key'));
        $secret = trim((string) config('services.livekit.api_secret'));
        $url = trim((string) config('services.livekit.url'));
        if (preg_match('~^wss://[a-zA-Z0-9.-]+(?::\d+)?/?$~', $url) && $key && strlen($secret) >= 32) {
            $now = now()->timestamp;
            $adminToken = JWT::encode(['iss' => $key, 'iat' => $now, 'nbf' => $now - 5, 'exp' => $now + 60, 'video' => ['roomCreate' => true]], $secret, 'HS256');
            try {
                $response = Http::withToken($adminToken)->timeout(8)->post('https://'.substr(rtrim($url, '/'), 6).'/twirp/livekit.RoomService/DeleteRoom', ['room' => $this->roomName($application)]);
                $roomClosed = $response->successful() || ($response->status() === 404 && $response->json('code') === 'not_found');
            } catch (ConnectionException) {
                // No token/secret/request logs; clients observe persisted closure on the next poll.
            }
        }
        return response()->json([
            'status' => 'success', 'message' => 'Interview ended.', 'room_closed' => $roomClosed,
            'interview_call' => $this->availability($application, $request),
        ])->header('Cache-Control', 'no-store, private');
    }
}
