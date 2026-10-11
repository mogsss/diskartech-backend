<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Report;
use App\Services\UploadStorage;
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
                    $avatar = $user->studentProfile->avatar ?? $user->studentProfile->profile_picture;
                    $phone = $user->studentProfile->contact_number;
                    $isVerified = (bool) $user->studentProfile->isVerified;
                } elseif ($user->role === 'employer' && $user->employerProfile) {
                    $displayName = $user->employerProfile->employer_name
                        ?? $user->email;
                    $avatar = $user->employerProfile->avatar ?? $user->employerProfile->profile_picture;
                    $phone = $user->employerProfile->contact_number;
                    $isVerified = (bool) $user->employerProfile->isVerified;
                } elseif ($user->role === 'household' && $user->householdProfile) {
                    $displayName = $user->householdProfile->household_name 
                        ?? $user->householdProfile->full_name 
                        ?? $user->email;
                    $avatar = $user->householdProfile->avatar ?? $user->householdProfile->profile_picture;
                    $phone = $user->householdProfile->cp_number;
                    $isVerified = (bool) $user->householdProfile->isVerified;
                }

                return [
                    (string) $user->id => [
                        'id' => $user->id,
                        'name' => $displayName,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar' => app(UploadStorage::class)->url($avatar),
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
