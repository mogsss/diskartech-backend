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

        return response()->json([
            'status' => 'success',
            'user' => $user,
            'profile' => $profile,
            // Pwede mo nang idagdag dito ang iba pang data tulad ng stats o posted jobs balang araw
        ], 200);
    }
}