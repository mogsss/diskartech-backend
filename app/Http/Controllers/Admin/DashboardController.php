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

        return view('admin.dashboard', compact(
            'users',
            'studentsCount',
            'householdCount',
            'employerCount'
        ));
    }
}