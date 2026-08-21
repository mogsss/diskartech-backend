<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; // 👈 Idinagdag ang Log facade

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

            $jobs = Job::where('status', 'active')
                ->with(['household', 'employer'])
                ->get();

            if ($jobs->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'matched_jobs' => []
                ], 200);
            }

            $studentSkills = is_string($student->skills) ? (json_decode($student->skills, true) ?? []) : ($student->skills ?? []);
            $studentDays = is_string($student->available_days) ? (json_decode($student->available_days, true) ?? []) : ($student->available_days ?? []);
            $studentTimeSlot = $student->time_slot ?? 'Whole Day';

            $prompt = "As an AI Job Matcher for working students, analyze the student's profile and the available jobs. " .
                      "Compute a schedule and skills match percentage (from 0 to 100) for each job based on availability, time slot, and skills. " .
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

            $apiKey = env('GEMINI_KEY');

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            $matchedJobs = [];

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
                
                $cleanJson = trim(str_replace(['```json', '```'], '', $geminiText));
                $cleanJson = trim(preg_replace('/^```[a-z]*\s+|\s+```$/i', '', $cleanJson));
                $aiScores = json_decode($cleanJson, true) ?? [];

                foreach ($jobs as $job) {
                    $scoreObj = collect($aiScores)->firstWhere('id', $job->id);
                    $matchScore = $scoreObj['match_percentage'] ?? 75;

                    $job->match_percentage = $matchScore;
                    $matchedJobs[] = $job;
                }
            } else {
                // I-log kung nag-fail ang Gemini API response
                Log::warning('Gemini Job Matching API failed, using fallback.', ['response' => $response->body()]);

                foreach ($jobs as $job) {
                    $jobDays = is_string($job->available_days) ? (json_decode($job->available_days, true) ?? []) : ($job->available_days ?? []);
                    $commonDays = array_intersect($studentDays, $jobDays);
                    $daysMatchScore = count($jobDays) > 0 ? (count($commonDays) / count($jobDays)) * 100 : 70;
                    
                    $job->match_percentage = round($daysMatchScore);
                    $matchedJobs[] = $job;
                }
            }

            usort($matchedJobs, function ($a, $b) {
                return $b->match_percentage <=> $a->match_percentage;
            });

            return response()->json([
                'status' => 'success',
                'matched_jobs' => $matchedJobs
            ], 200);

        } catch (\Exception $e) {
            // Itatala ang eksaktong error sa laravel.log file
            Log::error('JobMatchingController Error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Exception error: ' . $e->getMessage()
            ], 500);
        }
    }
}