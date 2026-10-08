<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Jobs;
use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class JobMatchingController extends Controller
{
    public function getMatchedJobs(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            $student = Student::where('user_id', $user->id)->first();

            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student profile not found.'
                ], 404);
            }

            $studentLat = $student->latitude ?? null;
            $studentLong = $student->longitude ?? null;

            if (!$studentLat || !$studentLong) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student location is not set in your profile.'
                ], 422);
            }

            // Kinuha ang mga jobs kasama ang distansya para mai-show sa UI nang hindi hinaharangan ng radius
            $jobs = Jobs::with(['household.user', 'employer.user', 'user'])
                ->withDistance($studentLat, $studentLong)
                ->where('status', 'active')
                ->get();

            if ($jobs->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'matched_by' => 'none',
                    'matched_jobs' => []
                ], 200);
            }

            $studentSkills = is_string($student->skills) ? (json_decode($student->skills, true) ?? []) : ($student->skills ?? []);
            if (empty($studentSkills) && !empty($student->skillset)) {
                $studentSkills = is_string($student->skillset) ? (json_decode($student->skillset, true) ?? []) : ($student->skillset ?? []);
            }

            $studentDays = is_string($student->available_days) ? (json_decode($student->available_days, true) ?? []) : ($student->available_days ?? []);
            $studentTimeSlot = $student->time_slot ?? 'Whole Day';

            // Cache check para mabilis (sub-second) at maiwasan ang Gemini rate limit / lag
            $cacheKey = "student_ai_match_v2_{$student->id}_" . md5(json_encode([
                'skills' => $studentSkills,
                'days' => $studentDays,
                'slot' => $studentTimeSlot,
                'job_ids' => $jobs->pluck('id')->toArray(),
            ]));

            if (Cache::has($cacheKey)) {
                $cached = Cache::get($cacheKey);
                return response()->json([
                    'status' => 'success',
                    'matched_by' => $cached['matched_by'] ?? 'cached',
                    'matched_jobs' => $cached['matched_jobs'] ?? [],
                ], 200);
            }

            // Prompt para kay Gemini AI (Purong Skills at Schedule lamang)
            $prompt = "As an AI Job Matcher for working students, analyze the student's profile and the available jobs. " .
                      "Compute a schedule and skills match percentage (from 0 to 100) for each job based strictly on availability, time slot, and skills compatibility. " .
                      "Return the response STRICTLY as a valid JSON array without any markdown backticks or extra text, where each item contains 'id' (job id) and 'match_percentage' (integer).\n\n" .
                      "Student Profile:\n" .
                      "- Skills: " . implode(', ', $studentSkills) . "\n" .
                      "- Available Days: " . implode(', ', $studentDays) . "\n" .
                      "- Time Slot: " . $studentTimeSlot . "\n\n" .
                      "Available Jobs:\n" . json_encode($jobs->map(function($j) {
                          return [
                              'id' => $j->id,
                              'title' => $j->title,
                              'category' => $j->category,
                              'available_days' => $j->available_days,
                              'time_slot' => $j->time_slot,
                              'skills_needed' => $j->skills
                          ];
                      }));

            $apiKey = config('services.gemini.key') ?: env('GEMINI_KEY');
            $geminiModel = config('services.gemini.model', 'gemini-3.5-flash-lite');
            $geminiTimeout = max(1, (int) config('services.gemini.matching_timeout', 15));
            $geminiConnectTimeout = min($geminiTimeout, max(1, (int) config('services.gemini.matching_connect_timeout', 5)));

            $matchedJobs = [];
            $matchedBySource = 'ai_skills_schedule';

            try {
                if (empty($apiKey)) {
                    throw new \Exception('Gemini API key is not configured.');
                }

                // Keep requests bounded, but allow more than four seconds for generation.
                $response = Http::connectTimeout($geminiConnectTimeout)->timeout($geminiTimeout)->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($geminiModel) . ':generateContent', [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $geminiData = $response->json();
                    $geminiText = '';

                    if (isset($geminiData['candidates'][0]['content']['parts'])) {
                        foreach ($geminiData['candidates'][0]['content']['parts'] as $part) {
                            if (isset($part['text']) && !empty(trim($part['text']))) {
                                $geminiText = $part['text'];
                                break;
                            }
                        }
                    }

                    $aiScores = $this->decodeScores($geminiText);

                    foreach ($jobs as $job) {
                        $scoreObj = collect($aiScores)->firstWhere('id', $job->id);
                        $aiMatchScore = $scoreObj['match_percentage'] ?? 0;

                        if ($aiMatchScore <= 0) {
                            continue;
                        }

                        $job->match_percentage = round($aiMatchScore);
                        $job->match_source = 'ai_skills_schedule';
                        $matchedJobs[] = $job;
                    }
                } else {
                    throw new \RuntimeException('Gemini returned an unsuccessful response.', $response->status());
                }
            } catch (\Exception $apiEx) {
                // Provider exception messages can contain credential-bearing URLs.
                Log::warning('Gemini Job Matching unavailable; checking backup.', [
                    'error_type' => get_class($apiEx),
                    'status' => $apiEx->getCode() ?: null,
                    'model' => $geminiModel,
                    'timeout_seconds' => $geminiTimeout,
                ]);

                // 👇 BACKUP AI: OpenAI / Codex / ChatGPT (gpt-4o-mini)
                $openaiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
                $openaiSuccess = false;
                $quotaCacheKey = 'job_matching:openai:quota:' . hash('sha256', $openaiKey ?? '');

                if (config('services.openai.matching_enabled', true) && !empty($openaiKey) && !Cache::has($quotaCacheKey)) {
                    try {
                        $openaiTimeout = max(1, (int) config('services.openai.matching_timeout', 5));
                        $openaiResponse = Http::connectTimeout(min(5, $openaiTimeout))->timeout($openaiTimeout)->withHeaders([
                            'Authorization' => "Bearer {$openaiKey}",
                            'Content-Type' => 'application/json',
                        ])->post('https://api.openai.com/v1/chat/completions', [
                            'model' => 'gpt-4o-mini',
                            'messages' => [
                                [
                                    'role' => 'system',
                                    'content' => 'You are an AI Job Matcher. Return strictly a raw JSON array of objects with "id" and "match_percentage" (integer from 0 to 100), with no markdown formatting or backticks.'
                                ],
                                [
                                    'role' => 'user',
                                    'content' => $prompt
                                ]
                            ],
                            'temperature' => 0.2,
                        ]);

                        if ($openaiResponse->successful()) {
                            $openaiData = $openaiResponse->json();
                            $openaiText = $openaiData['choices'][0]['message']['content'] ?? '';

                            $openaiScores = $this->decodeScores($openaiText);

                            if (is_array($openaiScores) && !empty($openaiScores)) {
                                foreach ($jobs as $job) {
                                    $scoreObj = collect($openaiScores)->firstWhere('id', $job->id);
                                    $aiMatchScore = $scoreObj['match_percentage'] ?? 0;

                                    if ($aiMatchScore <= 0) {
                                        continue;
                                    }

                                    $job->match_percentage = round($aiMatchScore);
                                    $job->match_source = 'ai_openai';
                                    $matchedJobs[] = $job;
                                }

                                // Valid zero scores are an AI result, not a provider failure.
                                $matchedBySource = 'ai_openai';
                                $openaiSuccess = true;
                            }
                        } else {
                            $quotaExhausted = in_array($openaiResponse->json('error.code'), [
                                'insufficient_quota',
                                'credit_balance_exhausted',
                                'organization_spend_limit_exceeded',
                                'organization_usage_limit_exceeded',
                            ], true)
                                || $openaiResponse->json('error.type') === 'insufficient_quota';

                            if ($quotaExhausted) {
                                Cache::put($quotaCacheKey, true, now()->addSeconds(max(1, (int) config('services.openai.matching_quota_cooldown', 1800))));
                            }

                            Log::warning('OpenAI Job Matching unavailable; using schedule fallback.', [
                                'status' => $openaiResponse->status(),
                                'reason' => $quotaExhausted ? 'quota_exhausted' : 'provider_error',
                            ]);
                        }
                    } catch (\Exception $openaiEx) {
                        Log::warning('OpenAI Job Matching failed; using schedule fallback.', ['error_type' => get_class($openaiEx)]);
                    }
                }

                // Mananatiling buo ang orihinal na fallback algorithm kapag nag-fail ang parehong AI
                if (!$openaiSuccess) {
                    $matchedBySource = 'fallback_math';

                    foreach ($jobs as $job) {
                        $jobDays = is_string($job->available_days) ? (json_decode($job->available_days, true) ?? []) : ($job->available_days ?? []);
                        $commonDays = array_intersect($studentDays, $jobDays);
                        $daysMatchScore = count($jobDays) > 0 ? (count($commonDays) / count($jobDays)) * 100 : 0;

                        if ($daysMatchScore <= 0) {
                            continue;
                        }

                        $job->match_percentage = round($daysMatchScore);
                        $job->match_source = 'fallback_math';
                        $matchedJobs[] = $job;
                    }
                }
            }

            // Pag-sort mula pinakamataas hanggang pinababang match percentage
            usort($matchedJobs, function ($a, $b) {
                return $b->match_percentage <=> $a->match_percentage;
            });

            // Retry AI sooner after a temporary outage; keep normal AI results for 30 minutes.
            Cache::put($cacheKey, [
                'matched_by' => $matchedBySource,
                'matched_jobs' => $matchedJobs,
            ], $matchedBySource === 'fallback_math'
                ? now()->addSeconds(max(1, (int) config('services.job_matching.fallback_cache_seconds', 60)))
                : now()->addMinutes(30));

            return response()->json([
                'status' => 'success',
                'matched_by' => $matchedBySource,
                'matched_jobs' => $matchedJobs
            ], 200);

        } catch (\Exception $e) {
            Log::error('JobMatchingController Error.', [
                'error_type' => get_class($e),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to retrieve job recommendations. Please try again.'
            ], 500);
        }
    }

    private function decodeScores(string $text): array
    {
        $cleanJson = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $scores = json_decode($cleanJson, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($scores) || !array_is_list($scores) || empty($scores)) {
            throw new \UnexpectedValueException('The provider did not return a score list.');
        }

        foreach ($scores as $score) {
            if (!is_array($score) || !isset($score['id'], $score['match_percentage'])
                || !is_numeric($score['id']) || !is_numeric($score['match_percentage'])
                || $score['match_percentage'] < 0 || $score['match_percentage'] > 100) {
                throw new \UnexpectedValueException('The provider returned an invalid matching score.');
            }
        }

        return $scores;
    }

    // Kunin ang mga malalapit na trabaho batay sa location (3km radius) gamit ang Haversine Model Scope
    public function getNearbyJobs(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student profile not found'], 404);
        }

        $studentLat = $student->latitude ?? null;
        $studentLong = $student->longitude ?? null;

        if (!$studentLat || !$studentLong) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student location is not set in your profile.'
            ], 422);
        }

        $radius = 3;

        $nearbyJobs = Jobs::with(['household.user', 'employer.user', 'user', 'applications'])
            ->withDistance($studentLat, $studentLong)
            ->where('status', 'active')
            ->having('distance', '<=', $radius)
            ->orderBy('distance', 'asc')
            ->get()
            ->map(function ($job) {
                $job->applications_count = $job->applications->count();
                return $job;
            });

        return response()->json([
            'status' => 'success',
            'count' => $nearbyJobs->count(),
            'jobs' => $nearbyJobs,
        ], 200);
    }
}
