<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\UploadStorage;
use App\Support\PlainTextInput;

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
        [$documentField, $validField, $remarksField] = match ($this->type) {
            'school_id' => ['school_id', 'school_id_ai_is_valid', 'school_id_ai_remarks'],
            'coe' => ['coe', 'coe_ai_is_valid', 'coe_ai_remarks'],
            'certificate' => ['employer_certificate_path', 'cert_ai_is_valid', 'cert_ai_remarks'],
            default => ['valid_id_path', 'ai_is_valid', 'ai_remarks'],
        };
        $profile = $this->profile->fresh();
        if (! $profile || $profile->{$documentField} !== $this->path) return;

        $profileClass = get_class($this->profile);
        $profileId = $this->profile->id ?? 'unknown';

        Log::info("AnalyzeVerificationDocument: Starting analysis for {$this->type} (Profile: {$profileClass} #{$profileId}, File: {$this->path})");

        if (!$apiKey) {
            Log::error('AnalyzeVerificationDocument: GEMINI_KEY is missing or empty in configuration.');
            return;
        }

        try {
            $fileData = base64_encode(app(UploadStorage::class)->read($this->path));
            
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
                Log::warning('AnalyzeVerificationDocument: AI service unavailable.', ['status' => $aiResponse->status()]);
                return;
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

            if (! is_array($parsedAi) || ! is_bool($parsedAi['is_valid'] ?? null)) {
                Log::warning('AnalyzeVerificationDocument: Invalid AI response; analysis remains pending.');
                return;
            }
            $remarks = is_string($parsedAi['remarks'] ?? null) ? PlainTextInput::normalize($parsedAi['remarks']) : '';
            if ($remarks === '' || PlainTextInput::containsMarkup($remarks)) $remarks = 'Document analysis completed.';
            // A re-upload during the network request must not receive stale results.
            $profile->newQuery()->whereKey($profile->id)->where($documentField, $this->path)->update([
                $validField => $parsedAi['is_valid'], $remarksField => mb_substr($remarks, 0, 255),
            ]);
            Log::info("AnalyzeVerificationDocument: Finished analyzing {$this->type} for {$profileClass} #{$profileId}");

        } catch (\Exception $e) {
            Log::warning('AnalyzeVerificationDocument: Analysis could not complete.', ['exception' => get_class($e)]);
        }
    }
}
