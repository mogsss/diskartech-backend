<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;

class InterviewCallController extends Controller
{
    public function token(Request $request, int $id)
    {
        $application = JobApplication::with(['job', 'student'])->findOrFail($id);
        $user = $request->user();
        $isStudent = $user->role === 'student' && (int) $application->student?->user_id === (int) $user->id;
        $isOwner = in_array($user->role, ['employer', 'household'], true)
            && (int) $application->job?->user_id === (int) $user->id;
        abort_unless($isStudent || $isOwner, 403, 'You cannot join this interview.');

        if ($application->status !== 'interview' || $application->interview_type !== 'online'
            || !$application->interview_date || !$application->interview_time) {
            return response()->json(['status' => 'error', 'message' => 'This application does not have an active online interview.'], 422);
        }

        $url = (string) config('services.livekit.url');
        $key = (string) config('services.livekit.api_key');
        $secret = (string) config('services.livekit.api_secret');
        if (!preg_match('~^wss://[a-zA-Z0-9.-]+(?::\d+)?/?$~', $url) || !$key || strlen($secret) < 32) {
            return response()->json(['status' => 'error', 'message' => 'Video calls are not configured yet. Please contact support.'], 503);
        }

        // Rescheduling creates a separate room; no client-selected room or identity is accepted.
        $schedule = hash('sha256', $application->interview_date.'|'.$application->interview_time);
        $room = 'interview-'.$application->id.'-'.substr($schedule, 0, 16);
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
        ])->header('Cache-Control', 'no-store, private');
    }
}
