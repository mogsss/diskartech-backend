<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Jobs;
use App\Models\Report;

class DashboardController extends Controller
{
    public function index()
    {
        $roleCounts = User::selectRaw('role, COUNT(*) AS total')->groupBy('role')->pluck('total', 'role');
        $studentsCount = (int) ($roleCounts['student'] ?? 0);
        $householdCount = (int) ($roleCounts['household'] ?? 0);
        $employerCount = (int) ($roleCounts['employer'] ?? 0);
        $openJobsCount = Jobs::where('status', 'active')->count();

        // The overview queue includes only complete submissions awaiting a decision.
        $profileTypes = [
            'student' => ['studentProfile', ['school_id', 'coe', 'student_resume']],
            'employer' => ['employerProfile', ['valid_id_path', 'employer_certificate_path']],
            'household' => ['householdProfile', ['valid_id_path']],
        ];
        $pendingQuery = User::where(function ($query) use ($profileTypes) {
            foreach ($profileTypes as $role => [$relation, $documents]) {
                $query->orWhere(function ($roleQuery) use ($role, $relation, $documents) {
                    $roleQuery->where('role', $role)->whereHas($relation, function ($profile) use ($documents) {
                        $profile->where('isVerified', false)
                            ->where(fn ($reason) => $reason->whereNull('rejection_reason')->orWhere('rejection_reason', ''));
                        foreach ($documents as $document) {
                            $profile->whereNotNull($document)->where($document, '!=', '');
                        }
                    });
                });
            }
        });
        $pendingVerificationCount = (clone $pendingQuery)->count();
        $users = $pendingQuery->with(['studentProfile', 'householdProfile', 'employerProfile'])
            ->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
        $flaggedReports = Report::with(['reporter.studentProfile', 'reporter.employerProfile', 'reporter.householdProfile', 'reportedUser', 'job'])
            ->whereIn('status', ['pending', 'investigating'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->take(3)
            ->get();
        $flaggedCount = Report::whereIn('status', ['pending', 'investigating'])->count();
        $generatedAt = now('Asia/Manila');
        $weekStart = $generatedAt->copy()->startOfDay()->subDays(6);
        $registrations = User::whereIn('role', array_keys($profileTypes))
            ->where('created_at', '>=', $weekStart->copy()->utc())
            ->where('created_at', '<=', $generatedAt->copy()->utc())
            ->get(['created_at'])->countBy(fn ($user) => $user->created_at->copy()->timezone('Asia/Manila')->format('Y-m-d'));
        $weeklyActivity = collect(range(0, 6))->map(function ($offset) use ($weekStart, $registrations) {
            $day = $weekStart->copy()->addDays($offset);
            return ['label' => $day->format('D'), 'date' => $day->format('M j'), 'count' => (int) ($registrations[$day->format('Y-m-d')] ?? 0)];
        });

        return view('admin.dashboard', compact(
            'users',
            'studentsCount',
            'householdCount',
            'employerCount',
            'flaggedReports',
            'flaggedCount',
            'pendingVerificationCount',
            'openJobsCount',
            'weeklyActivity',
            'generatedAt'
        ));
    }
}
