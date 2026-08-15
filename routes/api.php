<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/jobs', [JobController::class, 'store']);

// Mga API routes para sa Authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Route para sa Logout (Kailangan ng Sanctum token para gumana)
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);