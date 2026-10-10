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

        $url = trim((string) config('services.livekit.url'));
        $rawKey = (string) config('services.livekit.api_key');
        $rawSecret = (string) config('services.livekit.api_secret');
        $key = trim($rawKey);
        $secret = trim($rawSecret);
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

        $response = [
            'status' => 'success', 'server_url' => rtrim($url, '/'), 'token' => $token,
            'title' => $application->job?->title ?? 'Online interview',
        ];
        if ($request->boolean('diagnostics')) {
            // A comparison tag only: it cannot authenticate to LiveKit or reveal the secret.
            // The authorized caller already receives a JWT signed with this same secret.
            $response['diagnostics'] = [
                'signing_key_fingerprint' => substr(hash_hmac('sha256', 'diskartech-livekit-config-check-v1', $secret), 0, 16),
                'secret_length' => strlen($secret),
                'credential_whitespace_removed' => $rawKey !== $key || $rawSecret !== $secret,
            ];
        }

        return response()->json($response)->header('Cache-Control', 'no-store, private');
    }
}
