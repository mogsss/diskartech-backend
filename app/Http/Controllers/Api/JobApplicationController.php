<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\Job;
use App\Models\Student;

class JobApplicationController extends Controller
{
    public function applyJob(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            // Kunin ang student profile base sa user ID
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student profile not found.'
                ], 404);
            }

            // Validahin kung ibinigay ang job_id
            $request->validate([
                'job_id' => 'required|exists:available_jobs,id',
            ]);

            $jobId = $request->job_id;

            // Suriin kung nag-apply na ang estudyante sa trabahong ito dati
            $existingApplication = JobApplication::where('student_id', $student->id)
                ->where('job_id', $jobId)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($existingApplication) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already applied for this job.'
                ], 400);
            }

            // I-save ang bagong aplikasyon
            $application = JobApplication::create([
                'job_id' => $jobId,
                'student_id' => $student->id,
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Job application submitted successfully!',
                'data' => $application
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
    public function cancelApplication($id, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            // Kunin ang student profile base sa user ID (katulad ng sa applyJob)
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student profile not found.'
                ], 404);
            }

            // Gamitin ang JobApplication model at i-verify ang student_id
            $application = JobApplication::where('id', $id)
                ->where('student_id', $student->id)
                ->first();

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hindi natagpuan ang aplikasyon o wala kang pahintulot dito.'
                ], 404);
            }

            // I-check kung pending pa ang status bago payagang i-cancel
            if (strtolower($application->status) !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hindi na maaaring kanselahin ang aplikasyong ito dahil ito ay ' . $application->status . ' na.'
                ], 400);
            }

            // Baguhin ang status patungong cancelled
            $application->status = 'cancelled';
            $application->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Matagumpay na na-kansela ang aplikasyon.',
                'application' => $application
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'May naganap na server error.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
