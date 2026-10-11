<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\VerificationController; 
use App\Http\Controllers\Admin\UserController; // 
use App\Http\Controllers\Admin\JobModerationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\AccountController;

Route::get('/', function () {
    if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    return view('landing');
});

// Public Working Student Certificate Viewer & PDF Print Route
Route::get('/certificate/{id}/view', [\App\Http\Controllers\CertificateWebController::class, 'viewCertificate'])->name('certificate.view');

// Fallback direct storage file serving route for uploads and avatars
Route::get('/media/{path}', [\App\Http\Controllers\MediaController::class, 'document'])
    ->where('path', '.*')->middleware('signed')->name('media.document');

Route::get('/storage/{path}', function ($path) {
    app(\App\Services\UploadStorage::class)->assertSafePath($path);
    abort_if(str_starts_with($path, 'cloudinary/'), 404);
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if ($disk->exists($path)) {
        return response()->file($disk->path($path));
    }
    abort(404);
})->where('path', '.*');

// Guest Admin Routes (Login)
Route::middleware(['guest:admin'])->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.submit');
    Route::post('/login', [AuthController::class, 'login']);
});

// Admin Console Routes (Protected)
Route::middleware(['auth:admin', \App\Http\Middleware\EnsureSingleAdminSession::class])->group(function () {
    // Dashboard Route
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Verification Routes (Nakalipat na sa VerificationController)
    Route::get('/admin/verification', [VerificationController::class, 'verificationIndex'])->name('admin.verification');
    Route::post('/admin/verification/reject/{id}', [VerificationController::class, 'rejectVerification'])->name('admin.verification.reject');
    Route::post('/admin/verification/approve/{id}', [VerificationController::class, 'approveVerification'])->name('admin.verification.approve');
    Route::post('/admin/verification/retry-analysis/{id}', [VerificationController::class, 'retryAnalysis'])->middleware('throttle:10,1')->name('admin.verification.retry-analysis');

    // Users Route (Nakalipat na sa UserController)
    Route::get('/admin/users', [UserController::class, 'usersIndex'])->name('admin.users');

    Route::get('/admin/reviews', [ReviewController::class, 'index'])->name('admin.reviews');
    Route::get('/admin/account', [AccountController::class, 'edit'])->name('admin.account');
    Route::post('/admin/account/check-email', [AccountController::class, 'checkEmail'])->middleware('throttle:30,1')->name('admin.account.check-email');
    Route::patch('/admin/account', [AccountController::class, 'update'])->name('admin.account.update');
    Route::put('/admin/account/password', [AccountController::class, 'updatePassword'])->name('admin.account.password');

    // Job Moderation Routes
    Route::get('/admin/job-moderation', [JobModerationController::class, 'index'])->name('admin.job-moderation');
    Route::get('/admin/job-moderation/{id}', [JobModerationController::class, 'show'])->name('admin.job-moderation.show');
    Route::post('/admin/job-moderation/{id}/toggle', [JobModerationController::class, 'toggleStatus'])->name('admin.job-moderation.toggle');
    Route::patch('/admin/job-moderation/{id}/status', [JobModerationController::class, 'updateStatus'])->name('admin.job-moderation.status');
    Route::delete('/admin/job-moderation/{id}', [JobModerationController::class, 'destroy'])->name('admin.job-moderation.destroy');

    // Reports & Analytics Routes
    Route::get('/admin/reports', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('/admin/notifications/poll', [ReportController::class, 'pollNotifications'])->name('admin.notifications.poll');
    Route::patch('/admin/reports/{id}/status', [ReportController::class, 'updateStatus'])->name('admin.reports.status');
    Route::delete('/admin/reports/{id}', [ReportController::class, 'destroy'])->name('admin.reports.destroy');

    // Communications & Messages Moderation Route
    Route::get('/admin/messages', [MessageController::class, 'index'])->name('admin.messages');

    Route::get('/admin/settings', function () {
        return view('admin.settings');
    })->name('admin.settings');

    // Logout Route
    Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');
});
