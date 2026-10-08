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

            // Siguraduhing verified ang student bago makapag-apply
            if (!$student->isVerified) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Verify your account first before applying for jobs.',
                    'needs_verification' => true,
                ], 403);
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
                    'message' => 'Application not found or you do not have permission.'
                ], 404);
            }

            // I-check kung pending o viewed ang status bago payagang i-cancel
            $allowedStatuses = ['pending', 'viewed'];
            if (!in_array(strtolower($application->status), $allowedStatuses)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This application cannot be cancelled because its status is already ' . $application->status . '.'
                ], 400);
            }

            // Baguhin ang status patungong cancelled
            $application->status = 'cancelled';
            $application->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Application cancelled successfully.',
                'application' => $application
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'A server error occurred while cancelling the application.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteApplication($id, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student profile not found.'
                ], 404);
            }

            $application = JobApplication::where('id', $id)
                ->where('student_id', $student->id)
                ->first();

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found or you do not have permission.'
                ], 404);
            }

            $allowedStatuses = ['cancelled', 'rejected', 'completed', 'terminated'];
            if (!in_array(strtolower($application->status), $allowedStatuses)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Active applications cannot be deleted. Please cancel the application first.'
                ], 400);
            }

            $application->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Application deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'A server error occurred while deleting the application.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getApplicationDetails($id, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Student profile not found.'
                ], 404);
            }

            $application = JobApplication::with(['job.household.user', 'job.employer.user', 'job.user'])
                ->where('id', $id)
                ->where('student_id', $student->id)
                ->first();

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found or you do not have permission.'
                ], 404);
            }

            // Check if this student has already submitted an incident report for this job or hirer
            $hasReported = \App\Models\Report::where('reporter_id', $user->id)
                ->where(function ($q) use ($application) {
                    $q->where('job_id', $application->job_id);
                    if ($application->job && $application->job->user_id) {
                        $q->orWhere('reported_user_id', $application->job->user_id);
                    }
                })
                ->exists();

            $application->has_reported = $hasReported;

            return response()->json([
                'status' => 'success',
                'application' => $application,
                'has_reported' => $hasReported,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}


