<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StudentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/jobs', [JobController::class, 'store']);

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


    // Student Specific Routes (Profile at Availability)
    Route::get('/student/profile', [StudentController::class, 'getProfile']);
    Route::post('/student/update-availability', [StudentController::class, 'updateAvailability']);
    Route::post('/student/update-skills', [StudentController::class, 'updateSkills']);
    Route::post('/student/upload-doc', [StudentController::class, 'uploadStudentDoc']);

    // Logout Route
    Route::post('/logout', [AuthController::class, 'logout']);

    // Verification Document Upload
    Route::post('/upload-verification-doc', [AuthController::class, 'uploadVerificationDoc']);
});