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


class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. Dynamic Validation base sa role na pumapasok
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:student,employer,household',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'required|string',
            
            // Student specific validation
            'school_name' => 'required_if:role,student|nullable|string',
            'course' => 'required_if:role,student|nullable|string',
            'year_level' => 'required_if:role,student|nullable|string',

            // Employer specific validation
            'business_name' => 'required_if:role,employer|nullable|string',
            'business_type' => 'required_if:role,employer|nullable|string',

            // Common optional validations
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

        // Gamitin ang Database Transaction para sa sabay-sabay na pag-save o rollback
        DB::beginTransaction();

        try {
            // 2. I-save muna sa 'users' table ang login credentials
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            // 3. I-save sa kani-kanilang profile table depende sa role
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
                'message' => $e->getMessage(), // <--- Ipapakita nito sa app ang totoong error mula sa Database/Laravel
                'file' => $e->getFile(),         // <--- Anong file nagka-error
                'line' => $e->getLine(),         // <--- Anong line number
            ], 500);
        }
    }
    public function login(Request $request)
    {
        // 1. Validation
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

        // 2. I-check ang Email at Password
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password.'
            ], 401);
        }

        // 3. Kunin ang profile details depende sa role ng user
        $profile = null;
        if ($user->role === 'student') {
            $profile = Student::where('user_id', $user->id)->first();
        } elseif ($user->role === 'employer') {
            $profile = Employer::where('user_id', $user->id)->first();
        } elseif ($user->role === 'household') {
            $profile = Household::where('user_id', $user->id)->first();
        }

        // 4. Mag-generate ng token (Opsyonal pero recommended kung gumagamit ka ng Laravel Sanctum)
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
        // Buburahin ang token ng user na kasalukuyang naka-login
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.'
        ], 200);
    }
}