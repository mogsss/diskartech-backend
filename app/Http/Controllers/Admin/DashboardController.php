<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $studentsCount = User::where('role', 'student')->count();
        $householdCount = User::where('role', 'household')->count();
        $employerCount = User::where('role', 'employer')->count();

        $users = User::with(['studentProfile', 'householdProfile', 'employerProfile'])
            ->latest()
            ->get();
        $flaggedReports = \App\Models\Report::with(['reporter', 'reportedUser', 'job'])
            ->whereIn('status', ['pending', 'investigating'])
            ->latest()
            ->take(3)
            ->get();
        $flaggedCount = \App\Models\Report::whereIn('status', ['pending', 'investigating'])->count();

        return view('admin.dashboard', compact(
            'users',
            'studentsCount',
            'householdCount',
            'employerCount',
            'flaggedReports',
            'flaggedCount'
        ));
    }
}