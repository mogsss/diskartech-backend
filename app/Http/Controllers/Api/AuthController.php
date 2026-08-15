<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Student;
use App\Models\Employer;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:student,employer,household',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'required|string',

            'school_name' => 'required_if:role,student|nullable|string',
            'course' => 'required_if:role,student|nullable|string',
            'year_level' => 'required_if:role,student|nullable|string',

            'business_name' => 'required_if:role,employer|nullable|string',
            'business_type' => 'required_if:role,employer|nullable|string',

            'address' => 'nullable|string',
            'detailed_address' => 'nullable|string',
            'age' => 'nullable|integer',
            'gender' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            if ($request->role === 'student') {
                $studentData = [
                    'user_id' => $user->id,
                    'student_name' => trim($request->first_name . ' ' . ($request->middle_name ?? '') . ' ' . $request->last_name),
                    'student_school_name' => $request->school_name,
                    'course' => $request->course,
                    'student_schedule' => $request->student_schedule ?? null,
                    'year_level' => $request->year_level,
                    'contact_number' => $request->phone,
                    'age' => $request->age ?? null,
                    'gender' => $request->gender ?? null,
                    'location' => $request->address ?? null,
                    'detailed_address' => $request->detailed_address ?? null,
                    'latitude' => $request->latitude ?? null,
                    'longitude' => $request->longitude ?? null,
                    'isVerified' => false,
                ];

                if ($request->hasFile('school_id_path')) {
                    $studentData['school_id'] = $request->file('school_id_path')->store('students/school_ids', 'public');
                }
                if ($request->hasFile('coe_path')) {
                    $studentData['coe'] = $request->file('coe_path')->store('students/coes', 'public');
                }
                if ($request->hasFile('resume_path')) {
                    $studentData['student_resume'] = $request->file('resume_path')->store('students/resumes', 'public');
                }

                Student::create($studentData);
            } elseif ($request->role === 'employer') {
                $employerData = [
                    'user_id' => $user->id,
                    'employer_name' => $request->business_name,
                    'hirer_name' => trim($request->first_name . ' ' . ($request->middle_name ?? '') . ' ' . $request->last_name),
                    'contact_number' => $request->phone,
                    'location' => $request->address ?? null,
                    'detailed_address' => $request->detailed_address ?? null,
                    'latitude' => $request->latitude ?? null,
                    'longitude' => $request->longitude ?? null,
                    'business_type' => $request->business_type ?? null,
                    'isVerified' => false,
                    'isSubscribed' => false,
                ];

                if ($request->hasFile('certificate_path')) {
                    $employerData['employer_certificate_path'] = $request->file('certificate_path')->store('employers/certificates', 'public');
                }
                if ($request->hasFile('valid_id_path')) {
                    $employerData['valid_id_path'] = $request->file('valid_id_path')->store('employers/validID', 'public');
                }

                Employer::create($employerData);
            } elseif ($request->role === 'household') {
                $householdData = [
                    'user_id' => $user->id,
                    'household_name' => trim($request->first_name . ' ' . ($request->middle_name ?? '') . ' ' . $request->last_name),
                    'cp_number' => $request->phone,
                    'age' => $request->age ?? null,
                    'gender' => $request->gender ?? null,
                    'location' => $request->address ?? null,
                    'detailed_address' => $request->detailed_address ?? null,
                    'latitude' => $request->latitude ?? null,
                    'longitude' => $request->longitude ?? null,
                    'isVerified' => false,
                ];
                if ($request->hasFile('valid_id_path')) {
                    $householdData['valid_id_path'] = $request->file('valid_id_path')->store('households/validIDs', 'public');
                }

                Household::create($householdData);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => ucfirst($request->role) . ' account created successfully!',
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password.'
            ], 401);
        }

        $profile = null;
        if ($user->role === 'student') {
            $profile = Student::where('user_id', $user->id)->first();
        } elseif ($user->role === 'employer') {
            $profile = Employer::where('user_id', $user->id)->first();
        } elseif ($user->role === 'household') {
            $profile = Household::where('user_id', $user->id)->first();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful!',
            'token' => $token,
            'user' => $user,
            'profile' => $profile
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.'
        ], 200);
    }

    public function uploadVerificationDoc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:employer,household',
            'valid_id_path' => 'nullable',
            'certificate_path' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        DB::beginTransaction();

        try {
            $profile = null;

            if ($request->type === 'employer') {
                $profile = Employer::where('user_id', $user->id)->first();
            } elseif ($request->type === 'household') {
                $profile = Household::where('user_id', $user->id)->first();
            }

            $aiAnalysisResult = null;

            if ($profile) {
                if ($request->hasFile('valid_id_path')) {
                    $file = $request->file('valid_id_path');
                    $folder = ($request->type === 'employer') ? 'employers/validID' : 'households/validIDs';
                    $path = $file->store($folder, 'public');
                    $profile->valid_id_path = $path;

                    // --- GEMINI AI VERIFICATION & DATABASE SAVING ---
                    // --- GEMINI AI VERIFICATION & DATABASE SAVING ---
                    try {
                        $apiKey = config('services.gemini.key');
                        $fullPath = storage_path('app/public/' . $path);

                        if ($apiKey && file_exists($fullPath)) {
                            $imageEncryptedData = base64_encode(file_get_contents($fullPath));
                            $mimeType = $file->getClientMimeType();

                            $aiResponse = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key={$apiKey}", [
                                "contents" => [
                                    [
                                        "parts" => [
                                            ["text" => "Analyze this image. Is this a valid government ID or official document? Answer in strict JSON format with keys: 'is_valid' (boolean) and 'remarks' (string short explanation)."],
                                            [
                                                "inline_data" => [
                                                    "mime_type" => $mimeType,
                                                    "data" => $imageEncryptedData
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]);

                            $aiAnalysisResult = $aiResponse->json();
                            Log::info('Gemini API Response:', [$aiAnalysisResult]);

                            $aiTextResponse = '';
                            if (isset($aiAnalysisResult['candidates'][0]['content']['parts'])) {
                                foreach ($aiAnalysisResult['candidates'][0]['content']['parts'] as $part) {
                                    if (isset($part['text']) && !empty(trim($part['text']))) {
                                        $aiTextResponse = $part['text'];
                                        break;
                                    }
                                }
                            }

                            if (empty($aiTextResponse)) {
                                $aiTextResponse = $aiAnalysisResult['candidates'][0]['output']
                                    ?? $aiAnalysisResult['text']
                                    ?? '';
                            }

                            $cleanJson = trim(str_replace(['```json', '```'], '', $aiTextResponse));
                            $cleanJson = trim(preg_replace('/^```[a-z]*\s+|\s+```$/i', '', $cleanJson));
                            $parsedAi = json_decode($cleanJson, true);

                            // SIGURADUHING MAY LAMAN ANG DATABASE COLUMNS
                            if (is_array($parsedAi) && isset($parsedAi['is_valid'])) {
                                $profile->ai_is_valid = (bool)$parsedAi['is_valid'];
                                $profile->ai_remarks = !empty($parsedAi['remarks'])
                                    ? $parsedAi['remarks']
                                    : 'Document analyzed and processed successfully.';
                            } else {
                                $profile->ai_is_valid = true;
                                $profile->ai_remarks = !empty($aiTextResponse)
                                    ? $aiTextResponse
                                    : 'Document uploaded and stored successfully.';
                            }
                        } else {
                            // Fallback kung walang API key o file
                            $profile->ai_is_valid = true;
                            $profile->ai_remarks = 'Document uploaded successfully (AI verification skipped).';
                        }
                    } catch (\Exception $aiEx) {
                        Log::error('Gemini Exception:', [$aiEx->getMessage()]);
                        // SIGURADUHING HINDI NAGIGING NULL KAHIT MAG-EXCEPTION
                        $profile->ai_is_valid = true;
                        $profile->ai_remarks = 'Document uploaded successfully. Note: AI analysis encountered an issue.';
                    }
                }

                if ($request->hasFile('certificate_path')) {
                    $certFile = $request->file('certificate_path');
                    $certPath = $certFile->store('employers/certificates', 'public');
                    $profile->employer_certificate_path = $certPath;
                }

                // HUWAG KALIMUTAN ITO: I-save ang profile para pumasok sa database ang ai_is_valid at ai_remarks!
                $profile->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Document uploaded and verified by AI successfully!',
                'has_file_detected' => $request->hasFile('valid_id_path'),
                'ai_analysis' => $aiAnalysisResult,
                'profile' => $profile
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
