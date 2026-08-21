<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Job;

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
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return response()->json(['status' => 'error', 'message' => 'Student profile not found'], 404);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $docType = $request->document_type;

            // Piliin ang subfolder base sa document type
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

            // I-save sa storage/app/public/...
            $path = $file->storeAs($folderName, $filename, 'public');

            // 👇 DITO ANG PAGBABAGO: I-map ang 'profile_picture' papunta sa 'avatar' column
            $columnToUpdate = $docType;
            if ($docType === 'profile_picture') {
                $columnToUpdate = 'avatar';
            }

            // I-update ang kaukulang column sa students table
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
    // Kunin ang mga malalapit na trabaho batay sa 5km radius (Haversine Formula)
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

        $nearbyJobs = \App\Models\Job::with(['household', 'employer']) // 👈 Dito
            ->selectRaw(
                "*, 
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$studentLat, $studentLong, $studentLat]
            )
            ->where('status', 'active')
            ->having("distance", "<=", $radius)
            ->orderBy('distance', 'asc')
            ->get();

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

        // Idagdag ang Haversine calculation dito para sa lahat ng jobs
        $allJobs = \App\Models\Job::with(['household', 'employer'])
            ->selectRaw(
                "*, 
        (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$studentLat, $studentLong, $studentLat]
            )
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $allJobs->count(),
            'jobs' => $allJobs,
        ], 200);
    }
}
