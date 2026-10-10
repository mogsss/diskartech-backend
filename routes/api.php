<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\JobMatchingController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\EmployerController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\ContractReviewController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ==========================================
// Hiwalay na API routes para sa Registration
// ==========================================
Route::post('/register/student', [AuthController::class, 'registerStudent']);
Route::post('/register/employer', [AuthController::class, 'registerEmployer']);
Route::post('/register/household', [AuthController::class, 'registerHousehold']);

// Login Route
Route::post('/login', [AuthController::class, 'login']);
Route::post('/google-login', [AuthController::class, 'googleLogin']);

// Mga Routes na nangangailangan ng Sanctum Authentication
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/applications/{id}/interview-call/token', [\App\Http\Controllers\Api\InterviewCallController::class, 'token'])
        ->whereNumber('id')->middleware('throttle:20,1');
    // Dashboard Data
    Route::get('/dashboard-data', [DashboardController::class, 'getDashboardData']);

    // Job Posting (Naka-protect na para makuha ang user/employer location)
    Route::post('/jobs', [JobController::class, 'store']);
    Route::get('/jobs/{id}', [JobController::class, 'show']);
    Route::put('/jobs/{id}', [JobController::class, 'update']);
    Route::delete('/jobs/{id}', [JobController::class, 'destroy']);

    // Employer Management Routes (Pinag-isa na sa EmployerController)
    Route::post('/employer/add-profile', [EmployerController::class, 'addAvatar']);
    Route::get('/employer/jobs', [EmployerController::class, 'postedJobs']);
    Route::get('/employer/jobs/{jobId}/applicants', [EmployerController::class, 'getJobApplicants']);
    Route::patch('/employer/jobs/{jobId}/status', [EmployerController::class, 'updateJobStatus']);
    Route::get('/employer/applicants', [EmployerController::class, 'getAllApplicants']);
    Route::get('/employer/applications/{applicationId}', [EmployerController::class, 'getApplicationDetails']);
    Route::put('/employer/applications/{applicationId}/status', [EmployerController::class, 'updateApplicationStatus']);
    Route::delete('/employer/applications/{applicationId}', [EmployerController::class, 'deleteApplication']);

    // User Profile para sa Verification Status at Profile Updating
    Route::get('/user/profile', [AuthController::class, 'getUserProfile']);
    Route::get('/users/verification-statuses', [AuthController::class, 'getVerificationStatuses']);
    Route::get('/user/{id}/public-profile', [AuthController::class, 'getPublicUserProfile']);
    Route::post('/user/update-profile', [AuthController::class, 'updateUserProfile']);

    // ==========================================
    // OTP Email Verification Routes (Nasa loob ng Sanctum)
    // ==========================================
    Route::post('/email/send-otp', [AuthController::class, 'sendOtp'])->middleware(['throttle:6,1']);
    Route::post('/email/verify-otp', [AuthController::class, 'verifyOtp'])->middleware(['throttle:10,1,otp-verify']);

    // Student Specific Routes (Profile, Availability, at Nearby Jobs)
    Route::get('/student/profile', [StudentController::class, 'getProfile']);
    Route::post('/student/update-push-token', [StudentController::class, 'updatePushToken']);
    Route::post('/student/update-availability', [StudentController::class, 'updateAvailability']);
    Route::post('/student/update-skills', [StudentController::class, 'updateSkills']);
    Route::post('/student/upload-doc', [StudentController::class, 'uploadStudentDoc']);
    Route::get('/student/nearby-jobs', [JobMatchingController::class, 'getNearbyJobs']);
    Route::get('/student/all-jobs', [StudentController::class, 'getAllJobs']);
    Route::get('/student/applications', [StudentController::class, 'myApplications']);
    Route::get('/student/applications/{id}', [JobApplicationController::class, 'getApplicationDetails']);
    Route::get('/student/ai-matched-jobs', [JobMatchingController::class, 'getMatchedJobs']);
    Route::post('/student/apply-job', [JobApplicationController::class, 'applyJob']);
    Route::post('/student/applications/{id}/cancel', [JobApplicationController::class, 'cancelApplication']);
    Route::delete('/student/applications/{id}', [JobApplicationController::class, 'deleteApplication']);
    Route::post('/student/applications/{id}/delete', [JobApplicationController::class, 'deleteApplication']);
    Route::get('/student/saved-jobs', [StudentController::class, 'getSavedJobs']);
    Route::post('/student/toggle-save-job', [StudentController::class, 'toggleSaveJob']);

    // Working Student Certificate of Employment (COE) Routes
    Route::post('/student/applications/{id}/request-certificate', [CertificateController::class, 'requestCertificate']);
    Route::post('/employer/applications/{id}/approve-certificate', [CertificateController::class, 'approveCertificate']);
    Route::get('/applications/{id}/certificate-data', [CertificateController::class, 'getCertificateData']);

    // Contract Performance Rating & Review Routes
    Route::get('/applications/{id}/reviews', [ContractReviewController::class, 'getReviews']);
    Route::post('/applications/{id}/reviews', [ContractReviewController::class, 'submitReview']);

    // Incident & Safety Report Submission & History
    Route::post('/reports', [\App\Http\Controllers\Api\ReportApiController::class, 'store']);
    Route::get('/reports/my-reports', [\App\Http\Controllers\Api\ReportApiController::class, 'myReports']);

    // DiskarTech AI Support Chat
    Route::post('/support/ai-chat', [\App\Http\Controllers\Api\SupportAiController::class, 'chat']);

    // Logout Route
    Route::post('/logout', [AuthController::class, 'logout']);

    // Verification Document Upload
    Route::post('/upload-verification-doc', [VerificationController::class, 'uploadVerificationDoc']);
});
