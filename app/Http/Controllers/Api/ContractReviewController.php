<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\JobApplication;
use App\Models\Employer;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContractReviewController extends Controller
{
    /**
     * Resolve employer or household details from a job.
     */
    private function resolveEmployerInfo($job)
    {
        $employerUserId = null;
        $employerName = 'Employer';
        $employerAvatar = null;

        if (!$job) {
            return [$employerUserId, $employerName, $employerAvatar];
        }

        // Try relationship first, then direct query by user_id
        $employer = $job->employer ?? Employer::where('user_id', $job->user_id)->first();
        $household = $job->household ?? Household::where('user_id', $job->user_id)->first();

        if ($employer) {
            $employerUserId = $employer->user_id;
            $employerName = $employer->employer_name ?: ($employer->hirer_name ?: 'Employer');
            $employerAvatar = $employer->avatar;
        } elseif ($household) {
            $employerUserId = $household->user_id;
            $employerName = $household->household_name ?: 'Household Employer';
            $employerAvatar = $household->avatar;
        } else {
            $employerUserId = $job->user_id;
            $employerName = 'Employer';
        }

        return [$employerUserId, $employerName, $employerAvatar];
    }

    /**
     * Get review details and status for a specific application contract.
     */
    public function getReviews($applicationId, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
            }

            $application = JobApplication::with([
                'job.employer',
                'job.household',
                'job.user',
                'student.user'
            ])->find($applicationId);

            if (!$application) {
                return response()->json(['status' => 'error', 'message' => 'Application contract not found.'], 404);
            }

            // Identify user role and the other party (reviewee)
            $job = $application->job;
            $student = $application->student;

            $studentUserId = $student ? $student->user_id : null;
            $studentName = $student ? ($student->student_name ?: 'Student') : 'Student';
            $studentAvatar = $student ? $student->avatar : null;

            [$employerUserId, $employerName, $employerAvatar] = $this->resolveEmployerInfo($job);

            $isStudent = ($user->id == $studentUserId);
            $isEmployer = ($user->id == $employerUserId);

            if (!$isStudent && !$isEmployer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not a participant in this employment contract.'
                ], 403);
            }

            $currentUserRole = $isStudent ? 'student' : 'employer';
            $targetUserId = $isStudent ? $employerUserId : $studentUserId;
            $targetName = $isStudent ? $employerName : $studentName;
            $targetAvatar = $isStudent ? $employerAvatar : $studentAvatar;

            // Fetch reviews
            $allReviews = Review::where('application_id', $applicationId)->get();
            $myReview = $allReviews->firstWhere('reviewer_id', $user->id);
            $otherReview = $allReviews->firstWhere('reviewer_id', '!=', $user->id);

            $hasReviewed = !is_null($myReview);
            $canReview = in_array(strtolower($application->status), ['terminated', 'accepted', 'completed']) && !$hasReviewed;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'application_id' => $application->id,
                    'contract_status' => $application->status,
                    'job_title' => $job ? $job->title : 'Job Contract',
                    'user_role' => $currentUserRole,
                    'target_user' => [
                        'user_id' => $targetUserId,
                        'name' => $targetName,
                        'avatar' => $targetAvatar,
                        'role' => $isStudent ? 'employer' : 'student',
                    ],
                    'has_reviewed' => $hasReviewed,
                    'can_review' => $canReview,
                    'my_review' => $myReview,
                    'other_review' => $otherReview,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching reviews: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit an official review and rating for an application contract.
     */
    public function submitReview($applicationId, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'feedback' => 'nullable|string|max:1000',
                'tags' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $application = JobApplication::with([
                'job.employer',
                'job.household',
                'job.user',
                'student.user'
            ])->find($applicationId);

            if (!$application) {
                return response()->json(['status' => 'error', 'message' => 'Application contract not found.'], 404);
            }

            // Only allowed if status is terminated, accepted, or completed
            $allowedStatuses = ['terminated', 'accepted', 'completed'];
            if (!in_array(strtolower($application->status), $allowedStatuses)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reviews can only be submitted for concluded or active contracts.'
                ], 400);
            }

            $job = $application->job;
            $student = $application->student;

            $studentUserId = $student ? $student->user_id : null;
            [$employerUserId, $employerName, $employerAvatar] = $this->resolveEmployerInfo($job);

            $isStudent = ($user->id == $studentUserId);
            $isEmployer = ($user->id == $employerUserId);

            if (!$isStudent && !$isEmployer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to rate this contract.'
                ], 403);
            }

            // Check if already reviewed
            $existing = Review::where('application_id', $applicationId)
                ->where('reviewer_id', $user->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already submitted an official review for this contract.'
                ], 400);
            }

            $reviewerRole = $isStudent ? 'student' : 'employer';
            $revieweeId = $isStudent ? $employerUserId : $studentUserId;

            if (!$revieweeId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Could not determine the recipient for this review.'
                ], 400);
            }

            $review = Review::create([
                'application_id' => $application->id,
                'reviewer_id' => $user->id,
                'reviewee_id' => $revieweeId,
                'reviewer_role' => $reviewerRole,
                'rating' => $request->input('rating'),
                'feedback' => $request->input('feedback'),
                'tags' => $request->input('tags') ?? [],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Thank you! Your official rating and feedback have been submitted.',
                'review' => $review
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit review: ' . $e->getMessage()
            ], 500);
        }
    }
}
