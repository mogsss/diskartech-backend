<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function usersIndex(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');

        $query = User::with(['studentProfile', 'employerProfile', 'householdProfile']);

        if ($role && in_array($role, ['student', 'employer', 'household'])) {
            $query->where('role', $role);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', function ($subQ) use ($search) {
                        $subQ->where('student_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('employerProfile', function ($subQ) use ($search) {
                        $subQ->where('hirer_name', 'like', "%{$search}%")
                            ->orWhere('employer_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('householdProfile', function ($subQ) use ($search) {
                        $subQ->where('household_name', 'like', "%{$search}%");
                    });
            });
        }

        $users = $query->paginate(10)->appends(['search' => $search, 'role' => $role]);

        return view('admin.users', compact('users'));
    }
}