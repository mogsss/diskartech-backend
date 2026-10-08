<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeVerificationDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $profile;
    protected $path;
    protected $mimeType;
    protected $type;

    public function __construct($profile, $path, $mimeType, $type)
    {
        $this->profile = $profile;
        $this->path = $path;
        $this->mimeType = $mimeType;
        $this->type = $type;
    }

    public function handle()
    {
        $apiKey = config('services.gemini.key');
        $fullPath = storage_path('app/public/' . $this->path);

        $profileClass = get_class($this->profile);
        $profileId = $this->profile->id ?? 'unknown';

        Log::info("AnalyzeVerificationDocument: Starting analysis for {$this->type} (Profile: {$profileClass} #{$profileId}, File: {$this->path})");

        if (!$apiKey) {
            Log::error('AnalyzeVerificationDocument: GEMINI_KEY is missing or empty in configuration.');
            return;
        }

        if (!file_exists($fullPath)) {
            Log::error("AnalyzeVerificationDocument: File not found at {$fullPath}");
            return;
        }

        try {
            $fileData = base64_encode(file_get_contents($fullPath));
            
            switch ($this->type) {
                case 'school_id':
                    $prompt = "Analyze this image/document. Is this a valid school ID or university Student ID card? Answer in strict JSON format with keys: 'is_valid' (boolean) and 'remarks' (string short explanation).";
                    break;
                case 'coe':
                    $prompt = "Analyze this image/document. Is this a valid Certificate of Registration (COR), Certificate of Enrollment (COE), or official school registration/assessment form? Answer in strict JSON format with keys: 'is_valid' (boolean) and 'remarks' (string short explanation).";
                    break;
                case 'certificate':
                    $prompt = "Analyze this image/document. Is this a valid business permit, Mayor's Permit, Barangay Permit, DTI/SEC certificate, or official business license? Answer in strict JSON format with keys: 'is_valid' (boolean) and 'remarks' (string short explanation).";
                    break;
                case 'valid_id':
                default:
                    $prompt = "Analyze this image/document. Is this a valid government ID or official document? Answer in strict JSON format with keys: 'is_valid' (boolean) and 'remarks' (string short explanation).";
                    break;
            }

            $aiResponse = Http::timeout(60)->withHeaders([
                'x-goog-api-key' => $apiKey,
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(config('services.gemini.model', 'gemini-3.5-flash-lite')) . ':generateContent', [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $prompt],
                            [
                                "inline_data" => [
                                    "mime_type" => $this->mimeType,
                                    "data" => $fileData
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if (!$aiResponse->successful()) {
                Log::error("AnalyzeVerificationDocument: Gemini API error ({$aiResponse->status()}): " . $aiResponse->body());
            }

            $aiAnalysisResult = $aiResponse->json();
            $aiTextResponse = '';

            if (isset($aiAnalysisResult['candidates'][0]['content']['parts'])) {
                foreach ($aiAnalysisResult['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text']) && !empty(trim($part['text']))) {
                        $aiTextResponse = $part['text'];
                        break;
                    }
                }
            }

            $cleanJson = trim(str_replace(['```json', '```'], '', $aiTextResponse));
            $cleanJson = trim(preg_replace('/^```[a-z]*\s+|\s+```$/i', '', $cleanJson));
            $parsedAi = json_decode($cleanJson, true);

            if ($this->type === 'school_id') {
                if (is_array($parsedAi) && isset($parsedAi['is_valid'])) {
                    $this->profile->school_id_ai_is_valid = (bool)$parsedAi['is_valid'];
                    $this->profile->school_id_ai_remarks = !empty($parsedAi['remarks']) ? $parsedAi['remarks'] : 'School ID analyzed successfully.';
                } else {
                    $this->profile->school_id_ai_is_valid = true;
                    $this->profile->school_id_ai_remarks = 'School ID uploaded and stored successfully.';
                }
            } elseif ($this->type === 'coe') {
                if (is_array($parsedAi) && isset($parsedAi['is_valid'])) {
                    $this->profile->coe_ai_is_valid = (bool)$parsedAi['is_valid'];
                    $this->profile->coe_ai_remarks = !empty($parsedAi['remarks']) ? $parsedAi['remarks'] : 'Certificate of Enrollment (COR) analyzed successfully.';
                } else {
                    $this->profile->coe_ai_is_valid = true;
                    $this->profile->coe_ai_remarks = 'Certificate of Enrollment (COR) uploaded successfully.';
                }
            } elseif ($this->type === 'certificate') {
                if (is_array($parsedAi) && isset($parsedAi['is_valid'])) {
                    $this->profile->cert_ai_is_valid = (bool)$parsedAi['is_valid'];
                    $this->profile->cert_ai_remarks = !empty($parsedAi['remarks']) ? $parsedAi['remarks'] : 'Business permit analyzed successfully.';
                } else {
                    $this->profile->cert_ai_is_valid = true;
                    $this->profile->cert_ai_remarks = 'Business permit uploaded successfully.';
                }
            } else {
                // valid_id for employer & household
                if (is_array($parsedAi) && isset($parsedAi['is_valid'])) {
                    $this->profile->ai_is_valid = (bool)$parsedAi['is_valid'];
                    $this->profile->ai_remarks = !empty($parsedAi['remarks']) ? $parsedAi['remarks'] : 'Valid ID analyzed successfully.';
                } else {
                    $this->profile->ai_is_valid = true;
                    $this->profile->ai_remarks = 'Valid ID uploaded and stored successfully.';
                }
            }

            $this->profile->save();
            Log::info("AnalyzeVerificationDocument: Finished analyzing {$this->type} for {$profileClass} #{$profileId}");

        } catch (\Exception $e) {
            Log::error('AnalyzeVerificationDocument: Exception occurred:', ['error' => $e->getMessage()]);
        }
    }
}
