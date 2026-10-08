<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupportAiController extends Controller
{
    /**
     * Handle incoming chat messages with DiskarTech AI Support.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        $userMessage = trim($request->input('message'));
        $history = $request->input('history', []);

        $apiKey = config('services.gemini.key') ?: env('GEMINI_KEY');

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'reply' => 'Si DARA (24/7 Customer Support) ay kasalukuyang nagpapahinga o abala. Maaari mong tingnan ang aming FAQs o subukan muli mamaya.',
            ], 503);
        }

        $systemInstruction = <<<EOT
Ikaw si "DARA", ang opisyal na 24/7 AI customer support assistant ng DiskarTech mobile application sa Pilipinas.

ANG IYONG PANGUNAHING PAPEL AT SAKOP (SCOPE):
1. Tulungan ang mga working college students at employers/household hirers sa lahat ng bagay na may kinalaman sa DISKARTECH.
2. Mga Paksa na Sakop Mo:
   - Paghahanap at pag-apply sa student part-time jobs / gigs.
   - Paggamit ng AI Job Matching batay sa skills at schedule/availability ng estudyante.
   - Hiring process, interviews, contract issuance, at job completion.
   - Certificate of Completion / Certificate of Employment (CoE) release at approval.
   - Account verification (Student ID, COR, Employer Business Permits tulad ng Mayor's Permit, DTI/SEC, at Valid ID).
   - Rating at Review system (1 hanggang 5 stars, review tags, at constructive feedback).
   - Platform safety, pag-iwas sa scams, patas na pasahod (fair wage), at pag-file ng Incident Reports laban sa mga lumalabag.
   - Navigation sa DiskarTech app (Help Center, Saved Jobs, Applications, Profile).

MAHIGPIT NA PATAKARAN SA SAKOP (STRICT OUT-OF-SCOPE RULE):
- Ang buong kaalaman at sagot mo ay DAPAT TUNGKOL LAMANG SA DISKARTECH AT MGA SERBISYO NITO.
- Kung ang user ay nagtanong ng anumang bagay na WALANG KINALAMAN sa DiskarTech (halimbawa: homework sa math/science, essay writing, coding scripts na hindi tungkol sa app, pangkalahatang balita, tsismis sa showbiz, relasyon, laro, pangkalahatang kasaysayan, o politika):
  MAGALANG NA TUMANGGI at sabihin na ikaw si DARA na nakatuon lamang para sa mga tanong ukol sa DiskarTech, trabaho/gigs, account verification, at kaligtasan sa platform. Halimbawa:
  "Paumanhin, bilang si DARA (DiskarTech 24/7 Customer Support), nakatuon lamang ako sa pagsagot ng mga tanong tungkol sa DiskarTech platform, mga trabaho, account verification, at kaligtasan. May maitutulong ba ako tungkol sa iyong DiskarTech account o aplikasyon?"

ESTILO NG PAGSAGOT:
- Ipakilala ang sarili bilang DARA kapag binati ka o kapag angkop.
- Maging magiliw, propesyonal, at empatiya (Taglish o English depende sa wika ng user).
- Panatilihing maikli, malinaw, at madaling intindihin ang mga paliwanag (bullet points kung may steps).
- Huwag mag-imbento ng mga numero ng telepono o link sa labas ng DiskarTech.
EOT;

        // Build Gemini contents array with history
        $contents = [];

        // Include previous conversation history if provided (limit to last 6 turns to keep context fast)
        if (is_array($history) && count($history) > 0) {
            $recentHistory = array_slice($history, -6);
            foreach ($recentHistory as $turn) {
                if (!empty($turn['text'])) {
                    $role = ($turn['role'] ?? 'user') === 'model' ? 'model' : 'user';
                    $contents[] = [
                        'role' => $role,
                        'parts' => [
                            ['text' => $turn['text']]
                        ]
                    ];
                }
            }
        }

        // Add current user message
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $userMessage]
            ]
        ];

        try {
            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(config('services.gemini.model', 'gemini-3.5-flash-lite')) . ':generateContent', [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemInstruction]
                    ]
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 500,
                ]
            ]);

            if ($response->successful()) {
                $geminiData = $response->json();
                $replyText = '';

                if (isset($geminiData['candidates'][0]['content']['parts'])) {
                    foreach ($geminiData['candidates'][0]['content']['parts'] as $part) {
                        if (!empty($part['text'])) {
                            $replyText .= $part['text'];
                        }
                    }
                }

                $replyText = trim($replyText);

                if (empty($replyText)) {
                    $replyText = 'Salamat sa iyong mensahe! May katanungan ka ba tungkol sa iyong DiskarTech account o available na part-time jobs?';
                }

                return response()->json([
                    'status' => 'success',
                    'reply' => $replyText,
                ]);
            }

            Log::warning('Gemini AI Support returned error status:', ['status' => $response->status(), 'body' => $response->body()]);

            return response()->json([
                'status' => 'error',
                'reply' => 'Paumanhin, kasalukuyang abala ang aming AI support server. Maaari mong tingnan ang aming FAQs o subukang muli mamaya.',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Support AI Exception:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'reply' => 'Paumanhin, nagkaroon ng pansamantalang error sa koneksyon. Pakisubukang muli.',
            ], 500);
        }
    }
}
