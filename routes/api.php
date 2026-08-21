<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\VerificationController; 
use App\Http\Controllers\Api\JobMatchingController;

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

    Route::get('/student/ai-matched-jobs', [JobMatchingController::class, 'getMatchedJobs']);

    // Job Posting (Naka-protect na para makuha ang user/employer location)
    Route::post('/jobs', [JobController::class, 'store']);
    Route::get('/jobs/{id}', [JobController::class, 'show']);
    Route::get('/employer/my-jobs', [JobController::class, 'postedJobs']);

    // User Profile para sa Verification Status
    Route::get('/user/profile', [AuthController::class, 'getUserProfile']);

    // Student Specific Routes (Profile, Availability, at Nearby Jobs)
    Route::get('/student/profile', [StudentController::class, 'getProfile']);
    Route::post('/student/update-availability', [StudentController::class, 'updateAvailability']);
    Route::post('/student/update-skills', [StudentController::class, 'updateSkills']);
    Route::post('/student/upload-doc', [StudentController::class, 'uploadStudentDoc']);
    Route::get('/student/nearby-jobs', [StudentController::class, 'getNearbyJobs']); 
    Route::get('/student/all-jobs', [StudentController::class, 'getAllJobs']);

    // Logout Route
    Route::post('/logout', [AuthController::class, 'logout']);

    // Verification Document Upload
    Route::post('/upload-verification-doc', [VerificationController::class, 'uploadVerificationDoc']);
});