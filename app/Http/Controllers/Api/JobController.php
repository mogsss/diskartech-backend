<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use App\Models\Job;
use App\Models\Student;
use App\Models\Household;
use App\Models\Employer;

class JobController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'salary' => 'required|numeric',
            'category' => 'required|string', 
            'available_days' => 'required|array', // 👈 Validation para sa days array
            'time_slot' => 'required|string',       // 👈 Validation para sa time slot string
            'requirements' => 'nullable|array',
            'skills' => 'nullable|array',
        ]);

        $user = $request->user();
        
        $employerLat = null;
        $employerLong = null;

        // Kunin ang lokasyon base sa kung ang role ay household o employer
        if ($user->role === 'household') {
            $profile = Household::where('user_id', $user->id)->first();
            $employerLat = $profile->latitude ?? null;
            $employerLong = $profile->longitude ?? null;
        } elseif ($user->role === 'employer') {
            $profile = Employer::where('user_id', $user->id)->first();
            $employerLat = $profile->latitude ?? null;
            $employerLong = $profile->longitude ?? null;
        } else {
            // Fallback kung sakaling direkta sa users table nakalagay
            $employerLat = $user->latitude ?? null;
            $employerLong = $user->longitude ?? null;
        }

        if (!$employerLat || !$employerLong) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employer location is not set in your profile.'
            ], 422);
        }

        // I-save sa database gamit ang bagong columns para sa available_days at time_slot
        $job = Job::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'salary' => $request->salary,
            'category' => $request->category, 
            'available_days' => json_encode($request->available_days),
            'time_slot' => $request->time_slot,
            'requirements' => json_encode($request->requirements),
            'skills' => json_encode($request->skills),
            'latitude' => $employerLat,
            'longitude' => $employerLong,
            'status' => 'active',
        ]);

        // Haversine Formula para sa 5km radius matching ng students
        $radius = 5;
        $nearbyStudents = Student::selectRaw("*, 
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", 
            [$employerLat, $employerLong, $employerLat])
            ->having("distance", "<=", $radius)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Job posted successfully!',
            'job' => $job,
            'matched_students_count' => $nearbyStudents->count(),
        ], 201);
    }

    public function show($id)
    {
        $job = Job::with(['household', 'employer'])->find($id);

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'job' => $job
        ], 200);
    }
    public function postedJobs(Request $request)
{
    $user = $request->user();
    
    $jobs = Job::where('user_id', $user->id)->latest()->get();

    return response()->json([
        'status' => 'success',
        'jobs' => $jobs
    ], 200);
}
}