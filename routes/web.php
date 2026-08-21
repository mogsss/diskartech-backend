<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\VerificationController; 
use App\Http\Controllers\Admin\UserController; // 

Route::get('/', function () {
    if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    return view('landing');
});

// Guest Admin Routes (Login)
Route::middleware(['guest:admin'])->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'login']);
});

// Admin Console Routes (Protected)
Route::middleware(['auth:admin'])->group(function () {
    // Dashboard Route
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Verification Routes (Nakalipat na sa VerificationController)
    Route::get('/admin/verification', [VerificationController::class, 'verificationIndex'])->name('admin.verification');
    Route::post('/admin/verification/reject/{id}', [VerificationController::class, 'rejectVerification'])->name('admin.verification.reject');
    Route::post('/admin/verification/approve/{id}', [VerificationController::class, 'approveVerification'])->name('admin.verification.approve');

    // Users Route (Nakalipat na sa UserController)
    Route::get('/admin/users', [UserController::class, 'usersIndex'])->name('admin.users');

    // Static Pages / Other Routes
    Route::get('/admin/job-moderation', function () {
        return view('admin.job-moderation');
    })->name('admin.job-moderation');

    Route::get('/admin/settings', function () {
        return view('admin.settings');
    })->name('admin.settings');

    // Logout Route
    Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');
});