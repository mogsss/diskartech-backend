<?php

use App\Http\Controllers\Api\ChatAttachmentController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\CertificateWebController;
use App\Http\Controllers\MediaController;
use App\Services\UploadStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

$adminDomain = config('admin.domain');

// Register host-specific routes first so the admin homepage wins over landing.
if ($adminDomain) {
    Route::domain($adminDomain)->group(function () {
        Route::get('/', function () {
            return to_route(Auth::guard('admin')->check() ? 'admin.dashboard' : 'admin.login');
        })->name('admin.home');
        Route::get('/admin', fn () => to_route('admin.home'));
    });
}

Route::group($adminDomain ? ['domain' => $adminDomain] : [], base_path('routes/admin.php'));

Route::get('/', function () {
    if (! config('admin.domain') && Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }

    return view('landing');
});

// Public Working Student Certificate Viewer & PDF Print Route
Route::view('/privacy-policy', 'legal.policy', ['policyKey' => 'privacy'])->name('legal.privacy');
Route::view('/terms-and-conditions', 'legal.policy', ['policyKey' => 'terms'])->name('legal.terms');
Route::view('/cookie-policy', 'legal.policy', ['policyKey' => 'cookies'])->name('legal.cookies');

Route::get('/subscription/return', [SubscriptionController::class, 'returned'])->name('subscription.return');

Route::get('/certificate/{id}/view', [CertificateWebController::class, 'viewCertificate'])->name('certificate.view');

// Authorized chat attachments use short-lived application viewing links.
Route::get('/chat-attachments/{attachment}/view', [ChatAttachmentController::class, 'view'])
    ->whereUuid('attachment')->middleware('signed')->name('chat-attachments.view');

// Fallback direct storage file serving route for uploads and avatars
Route::get('/media/{path}', [MediaController::class, 'document'])
    ->where('path', '.*')->middleware('signed')->name('media.document');

Route::get('/storage/{path}', function ($path) {
    app(UploadStorage::class)->assertSafePath($path);
    abort_if(str_starts_with($path, 'cloudinary/'), 404);
    $disk = Storage::disk('public');
    if ($disk->exists($path)) {
        return response()->file($disk->path($path));
    }
    abort(404);
})->where('path', '.*');

// Admin routes only match the configured admin hostname. Other hosts and
// unknown paths use Laravel's normal 404 handling, including JSON API errors.
