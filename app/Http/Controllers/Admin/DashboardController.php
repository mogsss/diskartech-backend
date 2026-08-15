<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Bilangin nang hiwalay base sa aktwal na 'role' sa database mo
       $studentsCount = User::where('role', 'student')->count();
       $householdCount = User::where('role', 'household')->count();
       $employerCount = User::where('role', 'employer')->count();
        
        $openJobsCount = 0; 
        $openReportsCount = 0;

        // 2. Kunin ang mga tunay na users kasama ang kanilang mga profile relationships
        $users = User::with(['studentProfile', 'householdProfile', 'employerProfile'])
                    ->latest()
                    ->get();
                    
        $flaggedContent = []; 

        return view('admin.dashboard', compact(
            'users',
            'studentsCount',
            'householdCount',
            'employerCount'
        ));
    }
    public function verificationIndex()
    {
        $users = User::with(['studentProfile', 'householdProfile', 'employerProfile'])
                    ->latest()
                    ->get();
        
        $studentsCount = User::where('role', 'student')->count();
        $employerCount = User::where('role', 'employer')->count();
        $householdCount = User::where('role', 'household')->count();

        return view('admin.verification', compact(
            'users', 
            'studentsCount', 
            'employerCount', 
            'householdCount'
        ));
    }
}