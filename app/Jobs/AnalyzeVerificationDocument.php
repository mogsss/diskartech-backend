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

        if (!$apiKey || !file_exists($fullPath)) {
            return;
        }

        try {
            $fileData = base64_encode(file_get_contents($fullPath));
            
            $prompt = ($this->type === 'valid_id')
                ? "Analyze this image. Is this a valid government ID or official document? Answer in strict JSON format with keys: 'is_valid' (boolean) and 'remarks' (string short explanation)."
                : "Analyze this image. Is this a valid business permit, Mayor's Permit, Barangay Permit, DTI/SEC certificate, or official business license? Answer in strict JSON format with keys: 'is_valid' (boolean) and 'remarks' (string short explanation).";

            $aiResponse = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key={$apiKey}", [
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

            if ($this->type === 'valid_id') {
                if (is_array($parsedAi) && isset($parsedAi['is_valid'])) {
                    $this->profile->ai_is_valid = (bool)$parsedAi['is_valid'];
                    $this->profile->ai_remarks = !empty($parsedAi['remarks']) ? $parsedAi['remarks'] : 'Valid ID analyzed successfully.';
                } else {
                    $this->profile->ai_is_valid = true;
                    $this->profile->ai_remarks = 'Valid ID uploaded and stored successfully.';
                }
            } else {
                if (is_array($parsedAi) && isset($parsedAi['is_valid'])) {
                    $this->profile->cert_ai_is_valid = (bool)$parsedAi['is_valid'];
                    $this->profile->cert_ai_remarks = !empty($parsedAi['remarks']) ? $parsedAi['remarks'] : 'Business permit analyzed successfully.';
                } else {
                    $this->profile->cert_ai_is_valid = true;
                    $this->profile->cert_ai_remarks = 'Business permit uploaded successfully.';
                }
            }

            $this->profile->save();

        } catch (\Exception $e) {
            Log::error('Gemini Background Job Exception:', [$e->getMessage()]);
        }
    }
}