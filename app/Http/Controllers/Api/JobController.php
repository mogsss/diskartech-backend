<?php

namespace App\Http\Controllers\Api;

use App\Models\Jobs;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use App\Models\JobPosting;
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
            'available_days' => 'required|array', 
            'time_slot' => 'required|string',       
            'requirements' => 'nullable|array',
            'skills' => 'nullable|array',
        ]);

        $user = $request->user();
        
        $employerLat = null;
        $employerLong = null;

        // Kunin ang lokasyon at i-verify ang account base sa kung ang role ay household o employer
        if ($user->role === 'household') {
            $profile = Household::where('user_id', $user->id)->first();
            if (!$profile || !$profile->isVerified) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload required documents to post a job.',
                    'needs_verification' => true,
                ], 403);
            }
            $employerLat = $profile->latitude ?? null;
            $employerLong = $profile->longitude ?? null;
        } elseif ($user->role === 'employer') {
            $profile = Employer::where('user_id', $user->id)->first();
            if (!$profile || !$profile->isVerified) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload required documents to post a job.',
                    'needs_verification' => true,
                ], 403);
            }
            $employerLat = $profile->latitude ?? null;
            $employerLong = $profile->longitude ?? null;
        } else {
            // Fallback
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
        $job = Jobs::create([
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

        // Haversine Formula para sa 3-5km radius matching ng students
        $radius = 5; // Hanggang 5km radius
        $nearbyStudents = Student::selectRaw("id, user_id, student_name, latitude, longitude,
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", 
            [$employerLat, $employerLong, $employerLat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->having("distance", "<=", $radius)
            ->get();

        $matchedStudentsData = $nearbyStudents->map(function ($st) {
            $dist = round((float) $st->distance, 1);
            return [
                'user_id' => $st->user_id,
                'name' => $st->student_name,
                'distance' => $dist,
                'formatted_distance' => $dist < 0.1 ? '< 100m away' : ($dist < 1 ? round($dist * 1000) . 'm away' : $dist . ' km away'),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Job posted successfully!',
            'job' => $job,
            'matched_students_count' => $nearbyStudents->count(),
            'matched_students' => $matchedStudentsData,
        ], 201);
    }

    public function show($id, Request $request)
    {
        $job = Jobs::with(['household.user', 'employer.user', 'user'])->find($id);

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found'
            ], 404);
        }

        $hasReported = false;
        if ($request->user()) {
            $hasReported = \App\Models\Report::where('reporter_id', $request->user()->id)
                ->where(function ($q) use ($job) {
                    $q->where('job_id', $job->id);
                    if ($job->user_id) {
                        $q->orWhere('reported_user_id', $job->user_id);
                    }
                })
                ->exists();
        }

        $job->has_reported = $hasReported;

        return response()->json([
            'status' => 'success',
            'job' => $job,
            'has_reported' => $hasReported,
        ], 200);
    }
    

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'salary' => 'required|numeric',
            'category' => 'required|string', 
            'available_days' => 'required|array', 
            'time_slot' => 'required|string',       
            'requirements' => 'nullable|array',
            'skills' => 'nullable|array',
        ]);

        $user = $request->user();
        $job = Jobs::where('id', $id)->where('user_id', $user->id)->first();

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found or unauthorized'
            ], 404);
        }

        $job->update([
            'title' => $request->title,
            'description' => $request->description,
            'salary' => $request->salary,
            'category' => $request->category, 
            'available_days' => json_encode($request->available_days),
            'time_slot' => $request->time_slot,
            'requirements' => json_encode($request->requirements),
            'skills' => json_encode($request->skills),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Job updated successfully!',
            'job' => $job
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $job = Jobs::where('id', $id)->where('user_id', $user->id)->first();

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found or unauthorized'
            ], 404);
        }

        // Delete associated applications if any
        $job->applications()->delete();
        $job->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Job listing deleted successfully'
        ], 200);
    }
}
