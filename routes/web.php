<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController; // <--- 1. I-import ito sa taas

// Landing Page: Kapag naka-log in na ang admin, direkta siyang ibabalik sa dashboard!
Route::get('/', function () {
    if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    return view('landing');
});

// Guest Admin Routes (Login)
Route::middleware(['guest:admin'])->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login']);
});

// Admin Console Routes (Protected)
Route::middleware(['auth:admin'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

Route::get('/admin/verification', [DashboardController::class, 'verificationIndex'])->name('admin.verification');

    Route::get('/admin/job-moderation', function () {
        return view('admin.job-moderation');
    })->name('admin.job-moderation');

    Route::get('/admin/users', function () {
        return view('admin.users');
    })->name('admin.users');

    Route::get('/admin/settings', function () {
        return view('admin.settings');
    })->name('admin.settings');

    Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');
});