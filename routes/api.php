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

// Mga Routes na nangangailangan ng Sanctum Authentication
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard Data
    Route::get('/dashboard-data', [DashboardController::class, 'getDashboardData']);

    // Job Posting (Naka-protect na para makuha ang user/employer location)
    Route::post('/jobs', [JobController::class, 'store']);
    Route::get('/jobs/{id}', [JobController::class, 'show']);

    // Employer Management Routes (Pinag-isa na sa EmployerController)
    Route::get('/employer/jobs', [EmployerController::class, 'postedJobs']);
    Route::get('/employer/jobs/{jobId}/applicants', [EmployerController::class, 'getJobApplicants']);
    Route::get('/employer/applicants', [EmployerController::class, 'getAllApplicants']);
    Route::get('/employer/applications/{applicationId}', [EmployerController::class, 'getApplicationDetails']);
    Route::put('/employer/applications/{applicationId}/status', [EmployerController::class, 'updateApplicationStatus']);
    Route::delete('/employer/applications/{applicationId}', [EmployerController::class, 'deleteApplication']);

    // User Profile para sa Verification Status
    Route::get('/user/profile', [AuthController::class, 'getUserProfile']);

    // Student Specific Routes (Profile, Availability, at Nearby Jobs)
    Route::get('/student/profile', [StudentController::class, 'getProfile']);
    Route::post('/student/update-push-token', [StudentController::class, 'updatePushToken']);
    Route::post('/student/update-availability', [StudentController::class, 'updateAvailability']);
    Route::post('/student/update-skills', [StudentController::class, 'updateSkills']);
    Route::post('/student/upload-doc', [StudentController::class, 'uploadStudentDoc']);
    Route::get('/student/nearby-jobs', [StudentController::class, 'getNearbyJobs']);
    Route::get('/student/all-jobs', [StudentController::class, 'getAllJobs']);
    Route::get('/student/applications', [StudentController::class, 'myApplications']);
    Route::get('/student/ai-matched-jobs', [JobMatchingController::class, 'getMatchedJobs']);
    Route::post('/student/apply-job', [JobApplicationController::class, 'applyJob']);
    Route::post('/student/applications/{id}/cancel', [JobApplicationController::class, 'cancelApplication']);
    Route::get('/student/saved-jobs', [StudentController::class, 'getSavedJobs']);
    Route::post('/student/toggle-save-job', [StudentController::class, 'toggleSaveJob']);

    // Logout Route
    Route::post('/logout', [AuthController::class, 'logout']);

    // Verification Document Upload
    Route::post('/upload-verification-doc', [VerificationController::class, 'uploadVerificationDoc']);
});
