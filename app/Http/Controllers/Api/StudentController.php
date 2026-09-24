<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Jobs;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\JobApplication;

class StudentController extends Controller
{
    // Kunin ang profile ng student
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        return response()->json([
            'status' => 'success',
            'profile' => $student
        ], 200);
    }
    public function updatePushToken(Request $request)
    {
        $request->validate([
            'expo_push_token' => 'required|string',
        ]);

        $user = $request->user();

        // Siguraduhing student ang nag-a-update
        Student::where('user_id', $user->id)->update([
            'expo_push_token' => $request->expo_push_token,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Expo push token updated successfully.'
        ], 200);
    }

    public function updateAvailability(Request $request)
    {
        $request->validate([
            'days' => 'required|array',
            'time_slot' => 'required|string',
        ]);

        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if ($student) {
            $student->update([
                'available_days' => $request->days,
                'time_slot' => $request->time_slot,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Availability updated successfully!',
                'profile' => $student
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Student profile not found'
        ], 404);
    }

    public function updateSkills(Request $request)
    {
        $request->validate([
            'skills' => 'required|array',
        ]);

        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if ($student) {
            $student->update([
                'skillset' => $request->skills,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Skills updated successfully!',
                'profile' => $student
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Student profile not found'
        ], 404);
    }

    public function uploadStudentDoc(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string|in:student_resume,school_id,coe,profile_picture',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student profile not found'], 404);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $docType = $request->document_type;

            $folderName = 'student/';
            if ($docType === 'student_resume') {
                $folderName .= 'resume';
            } elseif ($docType === 'school_id') {
                $folderName .= 'school_id';
            } elseif ($docType === 'coe') {
                $folderName .= 'coe';
            } elseif ($docType === 'profile_picture') {
                $folderName .= 'profile_pictures';
            }

            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs($folderName, $filename, 'public');

            $columnToUpdate = $docType;
            if ($docType === 'profile_picture') {
                $columnToUpdate = 'avatar';
            }

            $student->update([
                $columnToUpdate => $path
            ]);

            return response()->json([
                $docType => $path,
                'status' => 'success',
                'message' => 'Document uploaded successfully!',
                'file_path' => $path,
                'docs_count' => $student->docs_count
            ], 200);
        }

        return response()->json(['status' => 'error', 'message' => 'No file uploaded'], 400);
    }

    // Kunin ang mga malalapit na trabaho batay sa 5km radius gamit ang Model Scope
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

        $radius = 5;

        $nearbyJobs = Jobs::with(['household', 'employer', 'applications'])
            ->withDistance($studentLat, $studentLong)
            ->where('status', 'active')
            ->having("distance", "<=", $radius)
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

    public function getAllJobs(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student profile not found'], 404);
        }

        $studentLat = $student->latitude;
        $studentLong = $student->longitude;

        $allJobs = Jobs::with(['household', 'employer', 'applications'])
            ->withDistance($studentLat, $studentLong)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                $job->applications_count = $job->applications->count();
                return $job;
            });

        return response()->json([
            'status' => 'success',
            'count' => $allJobs->count(),
            'jobs' => $allJobs,
        ], 200);
    }

    public function myApplications(Request $request)
    {
        $userId = $request->user()->id;

        $student = Student::where('user_id', $userId)->first();

        if (!$student) {
            return response()->json([
                'status' => 'success',
                'applications' => []
            ]);
        }

        // 👇 Isinama na natin ang job.household at job.employer para lumabas ang avatar/profile nila
        $applications = JobApplication::with(['job.household', 'job.employer'])
            ->where('student_id', $student->id)
            ->get();

        return response()->json([
            'status' => 'success',
            'applications' => $applications
        ]);
    }

    public function getSavedJobs(Request $request)
    {
        $student = Student::where('user_id', $request->user()->id)->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student not found'], 404);
        }

        $savedJobs = $student->savedJobs()
            ->with(['household', 'employer', 'applications'])
            ->latest()
            ->get()
            ->map(function ($job) {
                $job->applications_count = $job->applications->count();
                return $job;
            });

        return response()->json([
            'status' => 'success',
            'jobs' => $savedJobs
        ]);
    }

    public function toggleSaveJob(Request $request)
    {
        $request->validate(['job_id' => 'required|exists:available_jobs,id']);

        $student = Student::where('user_id', $request->user()->id)->first();
        $jobId = $request->job_id;

        if ($student->savedJobs()->where('job_id', $jobId)->exists()) {
            $student->savedJobs()->detach($jobId);
            return response()->json(['status' => 'success', 'message' => 'Job removed from saved items']);
        } else {
            $student->savedJobs()->attach($jobId);
            return response()->json(['status' => 'success', 'message' => 'Job saved successfully']);
        }
    }
}