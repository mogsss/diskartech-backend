<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Employer;
use App\Models\Household;

class DashboardController extends Controller
{
    public function getDashboardData(Request $request)
    {
        $user = $request->user(); // Kinukuha ang user gamit ang Sanctum token galing sa app
        $profile = null;

        // Kunin ang profile batay sa role ng nag-login
        if ($user->role === 'student') {
            $profile = Student::where('user_id', $user->id)->first();
        } elseif ($user->role === 'employer') {
            $profile = Employer::where('user_id', $user->id)->first();
        } elseif ($user->role === 'household') {
            $profile = Household::where('user_id', $user->id)->first();
        }

        $reviewQuery = \App\Models\Review::where('reviewee_id', $user->id);
        $reviewCount = $reviewQuery->count();
        $averageRating = $reviewCount > 0 ? round((float) $reviewQuery->avg('rating'), 1) : 5.0;

        return response()->json([
            'status' => 'success',
            'user' => $user,
            'profile' => $profile,
            'rating' => $averageRating,
            'review_count' => $reviewCount,
        ], 200);
    }
}