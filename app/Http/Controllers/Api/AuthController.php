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
use Illuminate\Support\Facades\Mail;
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

            Student::create($studentData);

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

            Employer::create($employerData);

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

            Household::create($householdData);

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

        return response()->json([
            'status' => 'success',
            'profile' => $profile
        ], 200);
    }

    // ==========================================
    // OTP: SEND OTP
    // ==========================================
    public function sendOtp(Request $request)
    {
        $user = $request->user();

        $otp = rand(100000, 999999);

        $user->otp_code = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        Mail::raw("Your DiskarTech verification code is: {$otp}. It expires in 10 minutes.", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Your Verification OTP Code');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'OTP has been sent to your email address.'
        ], 200);
    }

    // ==========================================
    // OTP: VERIFY OTP
    // ==========================================
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($user->otp_code !== $request->otp_code) {
            return response()->json(['status' => 'error', 'message' => 'Invalid OTP code.'], 400);
        }

        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['status' => 'error', 'message' => 'OTP code has expired. Please request a new one.'], 400);
        }

        $user->isEmailVerified = true;
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully!'
        ], 200);
    }
}