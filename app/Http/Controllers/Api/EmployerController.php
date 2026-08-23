<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\Jobs;

class EmployerController extends Controller
{
    // Ginagamit ito para sa dashboard (kasama na ang applications at student info)
    public function myJobListings(Request $request)
    {
        $userId = $request->user()->id;

        $jobs = Jobs::where('user_id', $userId)
            ->with(['applications.student']) // Isinama para makuha ang mga nag-apply
            ->withCount('applications')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'jobs' => $jobs
        ]);
    }

    public function getJobApplicants($jobId)
    {
        $applications = JobApplication::with(['student', 'job'])
            ->where('job_id', $jobId)
            ->get();

        return response()->json([
            'status' => 'success',
            'applicants' => $applications
        ]);
    }

    public function getAllApplicants(Request $request)
    {
        $userId = $request->user()->id;

        $applications = JobApplication::whereHas('job', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with(['student', 'job'])->get();

        return response()->json([
            'status' => 'success',
            'applicants' => $applications
        ]);
    }

    public function getApplicationDetails(Request $request, $applicationId)
    {
        $application = JobApplication::with(['student', 'job'])
            ->where('id', $applicationId)
            ->first();

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }
        if ($application->status === 'pending') {
            $application->status = 'viewed';
            $application->save();
        }

        return response()->json([
            'status' => 'success',
            'application' => $application
        ]);
    }

    public function updateApplicationStatus(Request $request, $applicationId)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected,interview,cancelled'
        ]);

        $application = JobApplication::where('id', $applicationId)->first();

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }

        $application->status = $request->status;
        $application->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Application status updated successfully',
            'application' => $application
        ]);
    }
    public function deleteApplication($applicationId)
    {
        try {
            $application = JobApplication::find($applicationId);

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found'
                ], 404);
            }

            // Optional: Pwede mo ring i-check kung ang employer/user ang may-ari ng job

            $application->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Application deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function postedJobs(Request $request)
    {
        return $this->myJobListings($request);
    }
}
