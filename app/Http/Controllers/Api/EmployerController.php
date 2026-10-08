<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\Jobs;

class EmployerController extends Controller
{
    public function addAvatar(Request $request)
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();
        $file = $request->file('profile_picture');
        $filename = time() . '_' . $file->getClientOriginalName();

        // 👇 Tukuyin ang folder depende sa role ng user
        if ($user->role === 'household') {
            $path = $file->storeAs('household/avatar', $filename, 'public');

            // Isinave na relative path lang (halimbawa: household/avatar/filename.jpg)
            \App\Models\Household::where('user_id', $user->id)->update([
                'avatar' => $path
            ]);
        } else {
            // Default para sa employer
            $path = $file->storeAs('employers/avatar', $filename, 'public');

            // Isinave na relative path lang (halimbawa: employers/avatar/filename.jpg)
            \App\Models\Employer::where('user_id', $user->id)->update([
                'avatar' => $path
            ]);
        }

        // Buong URL pa rin ang ibabato sa JSON response para sa mobile app
        $url = asset('storage/' . $path);

        return response()->json([
            'status' => 'success',
            'message' => 'Avatar updated successfully',
            'profile_picture' => $url
        ], 200);
    }
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
            'status' => 'required|in:accepted,rejected,interview,cancelled,terminated,completed',
            // Additional fields when hiring is confirmed
            'start_date' => 'sometimes|nullable|string|max:255',
            'end_date' => 'sometimes|nullable|string|max:255',
            'work_schedule' => 'sometimes|nullable|string|max:255',
            'agreed_rate' => 'sometimes|nullable|string|max:255',
            'special_instructions' => 'sometimes|nullable|string|max:2000',
            'contract_terms' => 'sometimes|nullable|string|max:2000',
            // Field when contract is terminated
            'termination_reason' => 'sometimes|nullable|string|max:1000',
        ]);

        $application = JobApplication::where('id', $applicationId)->first();

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }

        $application->status = $request->status;
        // If the application is being accepted, capture hiring details
        if ($request->status === 'accepted') {
            if ($request->has('start_date')) {
                $application->start_date = $request->start_date;
            }
            if ($request->has('end_date')) {
                $application->end_date = $request->end_date;
            }
            if ($request->has('work_schedule')) {
                $application->work_schedule = $request->work_schedule;
            }
            if ($request->has('agreed_rate')) {
                $application->agreed_rate = $request->agreed_rate;
            }
            if ($request->has('special_instructions')) {
                $application->special_instructions = $request->special_instructions;
            }
            if ($request->has('contract_terms')) {
                $application->contract_terms = $request->contract_terms;
            }
            // Record the timestamp when the hire is confirmed
            $application->hired_at = now();
        }

        // If the application / contract is being terminated
        if ($request->status === 'terminated') {
            $application->terminated_at = now();
            if ($request->has('termination_reason')) {
                $application->termination_reason = $request->termination_reason;
            }
        }

        if ($request->has('interview_date')) {
            $application->interview_date = $request->interview_date;
        }
        if ($request->has('interview_time')) {
            $application->interview_time = $request->interview_time;
        }
        if ($request->has('interview_location')) {
            $application->interview_location = $request->interview_location;
        }
        if ($request->has('interview_type')) {
            $application->interview_type = $request->interview_type;
        }

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

    public function updateJobStatus(Request $request, $jobId)
    {
        $request->validate([
            'status' => 'required|string|in:active,closed,inactive'
        ]);

        $userId = $request->user()->id;
        $job = \App\Models\Jobs::where('id', $jobId)->where('user_id', $userId)->first();

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found or unauthorized'
            ], 404);
        }

        $job->status = strtolower($request->status);
        $job->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Job status updated successfully',
            'job' => $job
        ]);
    }
}
