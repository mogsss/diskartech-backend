<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Jobs;
use App\Models\JobApplication;
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

        // Hiring outcomes belong to applications; one listing can have multiple applicants.
        $applicationCounts = JobApplication::selectRaw('LOWER(TRIM(status)) AS normalized_status, COUNT(*) AS total')
            ->groupByRaw('LOWER(TRIM(status))')->pluck('total', 'normalized_status');
        $jobApplicationsTotal = (int) $applicationCounts->sum();
        $jobStatusChart = collect([
            ['key' => 'pending', 'label' => 'Pending', 'color' => '#D97706', 'statuses' => ['pending']],
            ['key' => 'interview', 'label' => 'Interview', 'color' => '#0284C7', 'statuses' => ['interview']],
            ['key' => 'hired', 'label' => 'Hired', 'color' => '#4F46E5', 'statuses' => ['accepted', 'hired']],
            ['key' => 'completed', 'label' => 'Completed', 'color' => '#059669', 'statuses' => ['completed']],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'color' => '#64748B', 'statuses' => ['cancelled']],
            ['key' => 'rejected', 'label' => 'Rejected', 'color' => '#E11D48', 'statuses' => ['rejected']],
            ['key' => 'terminated', 'label' => 'Terminated', 'color' => '#C2410C', 'statuses' => ['terminated']],
        ])->map(function ($segment) use ($applicationCounts) {
            $segment['count'] = (int) collect($segment['statuses'])->sum(fn ($status) => $applicationCounts[$status] ?? 0);
            unset($segment['statuses']);
            return $segment;
        });
        $otherCount = $jobApplicationsTotal - $jobStatusChart->sum('count');
        if ($otherCount > 0) {
            $jobStatusChart->push(['key' => 'other', 'label' => 'Other', 'color' => '#78716C', 'count' => $otherCount]);
        }
        $jobStatusChart = $jobStatusChart->map(function ($segment) use ($jobApplicationsTotal) {
            $segment['percentage'] = $jobApplicationsTotal > 0 ? $segment['count'] / $jobApplicationsTotal * 100 : 0;
            return $segment;
        });

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
            'jobStatusChart',
            'jobApplicationsTotal',
            'weeklyActivity',
            'generatedAt'
        ));
    }
}
