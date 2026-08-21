<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
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

    public function rejectVerification(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|string|in:student,employer,household',
            'rejection_reason' => 'required|string|max:255',
        ]);

        $user = User::findOrFail($id);
        $profile = null;

        if ($request->role === 'employer') {
            $profile = $user->employerProfile;
        } elseif ($request->role === 'household') {
            $profile = $user->householdProfile;
        }

        if ($profile) {
            $profile->isVerified = false;
            $profile->rejection_reason = $request->rejection_reason;
            $profile->save();
        }

        return redirect()->back()->with('success', 'Application Rejected.');
    }

    public function approveVerification(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|string|in:student,employer,household',
        ]);

        $user = User::findOrFail($id);
        $profile = null;

        if ($request->role === 'employer') {
            $profile = $user->employerProfile;
        } elseif ($request->role === 'household') {
            $profile = $user->householdProfile;
        } elseif ($request->role === 'student') {
            $profile = $user->studentProfile;
        }

        if ($profile) {
            $profile->isVerified = true;
            $profile->rejection_reason = null;
            $profile->save();
        }

        return redirect()->back()->with('success', 'Verification approved successfully.');
    }
}