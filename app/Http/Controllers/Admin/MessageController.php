<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Jobs;
use App\Models\Report;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        // Gather database user directory for enriching chat participants
        $users = User::with(['studentProfile', 'employerProfile', 'householdProfile'])
            ->get()
            ->mapWithKeys(function ($user) {
                $displayName = $user->email;
                $avatar = null;
                $phone = null;
                $isVerified = false;

                if ($user->role === 'student' && $user->studentProfile) {
                    $displayName = $user->studentProfile->student_name ?? $user->email;
                    $avatar = $user->studentProfile->profile_picture;
                    $phone = $user->studentProfile->phone_number ?? null;
                    $isVerified = (bool) ($user->studentProfile->is_verified ?? false);
                } elseif ($user->role === 'employer' && $user->employerProfile) {
                    $displayName = $user->employerProfile->company_name 
                        ?? $user->employerProfile->employer_name 
                        ?? $user->email;
                    $avatar = $user->employerProfile->profile_picture ?? null;
                    $phone = $user->employerProfile->phone_number ?? null;
                    $isVerified = (bool) ($user->employerProfile->is_verified ?? false);
                } elseif ($user->role === 'household' && $user->householdProfile) {
                    $displayName = $user->householdProfile->household_name 
                        ?? $user->householdProfile->full_name 
                        ?? $user->email;
                    $avatar = $user->householdProfile->profile_picture ?? null;
                    $phone = $user->householdProfile->phone_number ?? null;
                    $isVerified = (bool) ($user->householdProfile->is_verified ?? false);
                }

                return [
                    (string) $user->id => [
                        'id' => $user->id,
                        'name' => $displayName,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar' => $avatar,
                        'phone' => $phone,
                        'is_verified' => $isVerified,
                        'status' => $user->status ?? 'active',
                    ]
                ];
            });

        // Summary counts
        $totalStudents = User::where('role', 'student')->count();
        $totalHirers = User::whereIn('role', ['employer', 'household'])->count();
        $openReportsCount = Report::whereIn('status', ['pending', 'investigating'])->count();

        // Firebase Client Configuration
        $firebaseConfig = [
            'apiKey' => 'AIzaSyCKnF-j0JhI5zOq03FY5tma-xId6YSywcc',
            'authDomain' => 'diskartech-2bf71.firebaseapp.com',
            'projectId' => 'diskartech-2bf71',
            'storageBucket' => 'diskartech-2bf71.firebasestorage.app',
            'messagingSenderId' => '408355828373',
            'appId' => '1:408355828373:web:e7282d58b06b58e2d7a19c',
            'measurementId' => 'G-1X8NTJB56N',
        ];

        return view('admin.messages', compact(
            'users',
            'totalStudents',
            'totalHirers',
            'openReportsCount',
            'firebaseConfig'
        ));
    }
}
