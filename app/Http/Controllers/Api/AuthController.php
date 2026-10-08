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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\GmailOtpMailer;
use App\Services\GmailDeliveryException;
use Carbon\Carbon;
use App\Jobs\AnalyzeVerificationDocument;

class AuthController extends Controller
{
    // ==========================================
    // 1. STUDENT REGISTRATION
    // ==========================================
    public function registerStudent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => ['required', 'string', 'min:8', 'regex:/[0-9]/'],
            'phone' => 'required|string',
            'school_name' => 'required|string',
            'course' => 'required|string',
            'year_level' => 'required|string',
            'address' => 'nullable|string',
            'detailed_address' => 'nullable|string',
            'age' => 'nullable|integer',
            'gender' => 'nullable|string',
        ], [
            'email.unique' => 'This email address is already registered.',
            'password.min' => 'The password must be at least 8 characters long.',
            'password.regex' => 'The password must contain at least one number.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'student',
                'isEmailVerified' => false,
            ]);

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

            $student = Student::create($studentData);

            if ($request->hasFile('school_id_path') && !empty($studentData['school_id'])) {
                AnalyzeVerificationDocument::dispatch($student, $studentData['school_id'], $request->file('school_id_path')->getClientMimeType(), 'school_id');
            }
            if ($request->hasFile('coe_path') && !empty($studentData['coe'])) {
                AnalyzeVerificationDocument::dispatch($student, $studentData['coe'], $request->file('coe_path')->getClientMimeType(), 'coe');
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Student account created successfully! Please verify your email.',
                'token' => $token,
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. EMPLOYER REGISTRATION
    // ==========================================
    public function registerEmployer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => ['required', 'string', 'min:8', 'regex:/[0-9]/'],
            'phone' => 'required|string',
            'business_name' => 'required|string',
            'business_type' => 'required|string',
            'address' => 'nullable|string',
            'detailed_address' => 'nullable|string',
        ], [
            'email.unique' => 'This email address is already registered.',
            'password.min' => 'The password must be at least 8 characters long.',
            'password.regex' => 'The password must contain at least one number.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'employer',
                'isEmailVerified' => false,
            ]);

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

            $employer = Employer::create($employerData);

            if ($request->hasFile('certificate_path') && !empty($employerData['employer_certificate_path'])) {
                AnalyzeVerificationDocument::dispatch($employer, $employerData['employer_certificate_path'], $request->file('certificate_path')->getClientMimeType(), 'certificate');
            }
            if ($request->hasFile('valid_id_path') && !empty($employerData['valid_id_path'])) {
                AnalyzeVerificationDocument::dispatch($employer, $employerData['valid_id_path'], $request->file('valid_id_path')->getClientMimeType(), 'valid_id');
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Employer account created successfully! Please verify your email.',
                'token' => $token,
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 3. HOUSEHOLD REGISTRATION
    // ==========================================
    public function registerHousehold(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => ['required', 'string', 'min:8', 'regex:/[0-9]/'],
            'phone' => 'required|string',
            'address' => 'nullable|string',
            'detailed_address' => 'nullable|string',
            'age' => 'nullable|integer',
            'gender' => 'nullable|string',
        ], [
            'email.unique' => 'This email address is already registered.',
            'password.min' => 'The password must be at least 8 characters long.',
            'password.regex' => 'The password must contain at least one number.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'household',
                'isEmailVerified' => false,
            ]);

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

            $household = Household::create($householdData);

            if ($request->hasFile('valid_id_path') && !empty($householdData['valid_id_path'])) {
                AnalyzeVerificationDocument::dispatch($household, $householdData['valid_id_path'], $request->file('valid_id_path')->getClientMimeType(), 'valid_id');
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Household account created successfully! Please verify your email.',
                'token' => $token,
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // LOGIN
    // ==========================================
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'The email address is not registered or does not exist.'], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'The password you entered is incorrect.'], 401);
        }

        // Suriin ang isEmailVerified column
        if (isset($user->isEmailVerified) && !$user->isEmailVerified) {
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'error',
                'message' => 'Your email address is not verified. Please check your inbox for the OTP code.',
                'token' => $token,
                'user' => $user // 👈 Isinama na natin ang user object dito
            ], 403);
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

    // ==========================================
    // GOOGLE LOGIN
    // ==========================================
    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'not_registered',
                'message' => 'Wala pang DiskarTech account na naka-link sa Google email na ito. Mangyaring mag-register muna.',
            ], 404);
        }

        // Suriin kung verified ang email
        if (isset($user->isEmailVerified) && !$user->isEmailVerified) {
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'error',
                'message' => 'Hindi pa verified ang iyong email. Mangyaring ilagay ang 6-digit OTP code na ipinadala sa iyong Gmail.',
                'token' => $token,
                'user' => $user
            ], 403);
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
            'message' => 'Google Login successful!',
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

    // ==========================================
    // GET USER PROFILE
    // ==========================================
    public function getUserProfile(Request $request)
    {
        $user = $request->user();
        
        $profile = $user->householdProfile ?? $user->employerProfile ?? $user->studentProfile;

        if ($profile) {
            $avatar = $profile->avatar ?? $profile->profile_picture ?? null;
            if ($avatar && !str_starts_with($avatar, 'http')) {
                $profile->avatar_url = asset('storage/' . $avatar);
            } else {
                $profile->avatar_url = $avatar;
            }
            $profile->avatar = $profile->avatar ?? $profile->profile_picture;
            $profile->profile_picture = $profile->profile_picture ?? $profile->avatar;
        }

        // Calculate average rating and review count from official contract reviews
        $reviewQuery = \App\Models\Review::where('reviewee_id', $user->id);
        $reviewCount = $reviewQuery->count();
        $averageRating = $reviewCount > 0 ? round((float) $reviewQuery->avg('rating'), 1) : 5.0;

        return response()->json([
            'status' => 'success',
            'profile' => $profile,
            'rating' => $averageRating,
            'review_count' => $reviewCount,
        ], 200);
    }

    public function getPublicUserProfile($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }

        $profile = $user->householdProfile ?? $user->employerProfile ?? $user->studentProfile;
        $isVerified = false;
        if ($profile) {
            $isVerified = (bool) ($profile->isVerified ?? false);
        }

        $name = $profile->student_name ?? $profile->household_name ?? $profile->employer_name ?? $user->name ?? 'User';
        $avatar = $profile->avatar ?? $profile->profile_picture ?? null;
        $avatarUrl = null;
        if ($avatar) {
            $avatarUrl = str_starts_with($avatar, 'http') ? $avatar : url('storage/' . $avatar);
        }

        // Calculate average rating and review count from official contract reviews
        $reviewQuery = \App\Models\Review::where('reviewee_id', $user->id);
        $reviewCount = $reviewQuery->count();
        $averageRating = $reviewCount > 0 ? round((float) $reviewQuery->avg('rating'), 1) : 5.0;

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'role' => $user->role,
                'name' => $name,
                'avatar' => $avatarUrl,
                'isVerified' => $isVerified,
                'rating' => $averageRating,
                'review_count' => $reviewCount,
                'email' => $user->email,
                'phone' => $profile->contact_number ?? null,
                'location' => $profile->location ?? null,
                'detailed_address' => $profile->detailed_address ?? null,
                'school' => $profile->student_school_name ?? null,
                'course' => $profile->course ?? null,
                'year_level' => $profile->year_level ?? null,
                'skills' => $profile->skillset ?? [],
                'business_name' => $profile->business_name ?? null,
                'business_type' => $profile->business_type ?? null,
                'about' => $profile->description ?? null,
            ]
        ], 200);
    }

    public function getVerificationStatuses(Request $request)
    {
        $ids = $request->query('ids');
        if (empty($ids)) {
            return response()->json(['status' => 'success', 'statuses' => (object)[]]);
        }

        $idArray = is_array($ids) ? $ids : explode(',', $ids);
        $idArray = array_filter(array_map('trim', $idArray));

        $users = User::whereIn('id', $idArray)
            ->with(['studentProfile', 'employerProfile', 'householdProfile'])
            ->get();

        $statuses = [];
        foreach ($users as $user) {
            $profile = $user->householdProfile ?? $user->employerProfile ?? $user->studentProfile;
            $name = $profile->student_name ?? $profile->household_name ?? $profile->employer_name ?? $user->name ?? 'User';
            $avatar = $profile->avatar ?? $profile->profile_picture ?? null;
            $avatarUrl = null;
            if ($avatar) {
                $avatarUrl = str_starts_with($avatar, 'http') ? $avatar : url('storage/' . $avatar);
            }

            $statuses[$user->id] = [
                'isVerified' => (bool) ($profile->isVerified ?? false),
                'name' => $name,
                'avatar' => $avatarUrl,
                'role' => $user->role,
            ];
        }

        return response()->json([
            'status' => 'success',
            'statuses' => (object) $statuses
        ]);
    }

    public function updateUserProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $student = \App\Models\Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json(['status' => 'error', 'message' => 'Student profile not found'], 404);
            }

            $updateData = [];
            if ($request->filled('first_name') && $request->filled('last_name')) {
                $updateData['student_name'] = trim($request->first_name . ' ' . ($request->middle_name ?? '') . ' ' . $request->last_name);
            } elseif ($request->filled('student_name')) {
                $updateData['student_name'] = $request->student_name;
            } elseif ($request->filled('name')) {
                $updateData['student_name'] = $request->name;
            }

            if ($request->filled('school')) $updateData['student_school_name'] = $request->school;
            if ($request->filled('student_school_name')) $updateData['student_school_name'] = $request->student_school_name;
            if ($request->filled('course')) $updateData['course'] = $request->course;
            if ($request->filled('year_level')) $updateData['year_level'] = $request->year_level;
            if ($request->filled('location')) $updateData['location'] = $request->location;
            if ($request->filled('detailed_address')) $updateData['detailed_address'] = $request->detailed_address;
            if ($request->filled('latitude')) $updateData['latitude'] = $request->latitude;
            if ($request->filled('longitude')) $updateData['longitude'] = $request->longitude;
            if ($request->filled('contact_number')) $updateData['contact_number'] = $request->contact_number;
            if ($request->filled('phone_number')) $updateData['contact_number'] = $request->phone_number;

            $student->update($updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Student profile updated successfully!',
                'profile' => $student,
                'user' => $user
            ], 200);
        } elseif ($user->role === 'employer') {
            $employer = \App\Models\Employer::where('user_id', $user->id)->first();
            if (!$employer) {
                return response()->json(['status' => 'error', 'message' => 'Employer profile not found'], 404);
            }

            $updateData = [];
            if ($request->filled('name')) $updateData['employer_name'] = $request->name;
            if ($request->filled('employer_name')) $updateData['employer_name'] = $request->employer_name;
            if ($request->filled('business_type')) $updateData['business_type'] = $request->business_type;
            if ($request->filled('location')) $updateData['location'] = $request->location;
            if ($request->filled('detailed_address')) $updateData['detailed_address'] = $request->detailed_address;
            if ($request->filled('latitude')) $updateData['latitude'] = $request->latitude;
            if ($request->filled('longitude')) $updateData['longitude'] = $request->longitude;
            if ($request->filled('contact_number')) $updateData['contact_number'] = $request->contact_number;

            $employer->update($updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Employer profile updated successfully!',
                'profile' => $employer,
                'user' => $user
            ], 200);
        } elseif ($user->role === 'household') {
            $household = \App\Models\Household::where('user_id', $user->id)->first();
            if (!$household) {
                return response()->json(['status' => 'error', 'message' => 'Household profile not found'], 404);
            }

            $updateData = [];
            if ($request->filled('name')) $updateData['household_name'] = $request->name;
            if ($request->filled('household_name')) $updateData['household_name'] = $request->household_name;
            if ($request->filled('location')) $updateData['location'] = $request->location;
            if ($request->filled('detailed_address')) $updateData['detailed_address'] = $request->detailed_address;
            if ($request->filled('latitude')) $updateData['latitude'] = $request->latitude;
            if ($request->filled('longitude')) $updateData['longitude'] = $request->longitude;
            if ($request->filled('contact_number')) $updateData['cp_number'] = $request->contact_number;
            if ($request->filled('cp_number')) $updateData['cp_number'] = $request->cp_number;

            $household->update($updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Household profile updated successfully!',
                'profile' => $household,
                'user' => $user
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid user role'], 400);
    }

    // ==========================================
    // OTP: SEND OTP
    // ==========================================
    public function sendOtp(Request $request, GmailOtpMailer $mailer)
    {
        $user = $request->user();
        $lock = Cache::lock('otp:send:'.$user->id, 30);

        if (! $lock->get()) {
            return response()->json([
                'status' => 'error',
                'message' => 'A verification email is already being sent. Please wait.',
                'mail_sent' => false,
                'retry_after' => 3,
            ], 429)->header('Retry-After', '3');
        }

        try {
            $user->refresh();
            if ($user->isEmailVerified) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your email address is already verified.',
                    'mail_sent' => false,
                ], 409);
            }

            $cooldown = max(1, (int) config('services.otp.resend_cooldown', 60));
            // The saved expiry also records when the last successful OTP was issued.
            $expiryMinutes = max(1, (int) config('services.otp.expiry_minutes', 5));
            $nextSendAt = $user->otp_expires_at?->copy()->subMinutes($expiryMinutes)->addSeconds($cooldown);
            if ($nextSendAt && $nextSendAt->isFuture()) {
                $retryAfter = max(1, (int) ceil(now()->diffInSeconds($nextSendAt)));

                return response()->json([
                    'status' => 'error',
                    'message' => "Please wait {$retryAfter} seconds before requesting another code.",
                    'mail_sent' => false,
                    'retry_after' => $retryAfter,
                ], 429)->header('Retry-After', (string) $retryAfter);
            }

            $otp = (string) random_int(100000, 999999);
            try {
                $mailer->sendVerificationCode($user->email, $otp);
            } catch (\Throwable $exception) {
                // Transport exceptions can include secrets; log only safe diagnostics.
                Log::warning('Gmail OTP sending failed.', [
                    'user_id' => $user->id,
                    'exception_type' => get_class($exception),
                    'reason' => $exception instanceof GmailDeliveryException
                        ? $exception->getMessage() : 'Transport or internal failure.',
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'We could not send your verification email. Please try again later.',
                    'mail_sent' => false,
                ], 503);
            }

            // Preserve the previous usable code if the provider fails.
            $user->otp_code = $otp;
            $user->otp_expires_at = now()->addMinutes($expiryMinutes);
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Verification email sent. Please check your inbox and spam folder.',
                'mail_sent' => true,
                'retry_after' => $cooldown,
            ], 200);
        } finally {
            $lock->release();
        }
    }

    // ==========================================
    // OTP: VERIFY OTP
    // ==========================================
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp_code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        $isMatch = $user->otp_code && hash_equals((string) $user->otp_code, (string) $request->otp_code);

        if (!$isMatch) {
            return response()->json(['status' => 'error', 'message' => 'Invalid OTP code.'], 400);
        }

        if (!$user->otp_expires_at || Carbon::now()->greaterThanOrEqualTo($user->otp_expires_at)) {
            return response()->json(['status' => 'error', 'message' => 'OTP code has expired. Please request a new one.'], 400);
        }

        $user->isEmailVerified = true;
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully!',
            'user' => $user
        ], 200);
    }
}
