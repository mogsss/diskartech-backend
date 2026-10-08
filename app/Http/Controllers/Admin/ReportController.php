<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Models\Student;
use App\Models\Employer;
use App\Models\Household;
use App\Models\Jobs;
use App\Models\JobApplication;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Display reports and system analytics.
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'incidents');
        $search = $request->input('search');
        $status = $request->input('status');
        $type = $request->input('type');

        // Query Incident Reports
        $reportsQuery = Report::with([
            'reporter.studentProfile',
            'reporter.employerProfile',
            'reporter.householdProfile',
            'reportedUser.studentProfile',
            'reportedUser.employerProfile',
            'reportedUser.householdProfile',
            'job'
        ]);

        if ($search) {
            $reportsQuery->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('id', $search);
                }
                $q->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('report_type', 'like', "%{$search}%")
                    ->orWhereHas('reporter', function ($sub) use ($search) {
                        $sub->where('email', 'like', "%{$search}%")
                            ->orWhereHas('studentProfile', function ($p) use ($search) {
                                $p->where('student_name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('employerProfile', function ($p) use ($search) {
                                $p->where('employer_name', 'like', "%{$search}%")
                                    ->orWhere('hirer_name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('householdProfile', function ($p) use ($search) {
                                $p->where('household_name', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('reportedUser', function ($sub) use ($search) {
                        $sub->where('email', 'like', "%{$search}%")
                            ->orWhereHas('studentProfile', function ($p) use ($search) {
                                $p->where('student_name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('employerProfile', function ($p) use ($search) {
                                $p->where('employer_name', 'like', "%{$search}%")
                                    ->orWhere('hirer_name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('householdProfile', function ($p) use ($search) {
                                $p->where('household_name', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('job', function ($sub) use ($search) {
                        $sub->where('title', 'like', "%{$search}%");
                    });
            });
        }

        if ($status && in_array($status, ['pending', 'investigating', 'resolved', 'dismissed'])) {
            $reportsQuery->where('status', $status);
        }

        if ($type && $type !== 'all') {
            $reportsQuery->where('report_type', $type);
        }

        $reports = $reportsQuery->orderBy('created_at', 'desc')->paginate(10)->appends($request->all());

        // Incident Reports Counters
        $incidentStats = [
            'total' => Report::count(),
            'pending' => Report::where('status', 'pending')->count(),
            'investigating' => Report::where('status', 'investigating')->count(),
            'resolved' => Report::where('status', 'resolved')->count(),
        ];

        // Available Report Types for filtering
        $types = ['scam', 'abuse', 'fake_job', 'underpaid', 'unsafe', 'no_show', 'other'];

        // Platform Analytics & System Overview
        $totalStudents = Student::count();
        $verifiedStudents = Student::where('isVerified', 1)->count();
        $totalEmployers = Employer::count();
        $verifiedEmployers = Employer::where('isVerified', 1)->count();
        $totalHouseholds = Household::count();
        $verifiedHouseholds = Household::where('isVerified', 1)->count();

        $totalProfiles = $totalStudents + $totalEmployers + $totalHouseholds;
        $totalVerified = $verifiedStudents + $verifiedEmployers + $verifiedHouseholds;
        $verificationRate = $totalProfiles > 0 ? round(($totalVerified / $totalProfiles) * 100, 1) : 0;

        $totalJobs = Jobs::count();
        $activeJobs = Jobs::where('status', 'active')->count();
        $hiddenJobs = Jobs::where('status', 'hidden')->count();
        $closedJobs = Jobs::where('status', 'closed')->count();

        // Jobs category breakdown
        $categoriesBreakdown = Jobs::select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($cat) use ($totalJobs) {
                return [
                    'name' => $cat->category ?: 'General',
                    'count' => $cat->total,
                    'percentage' => $totalJobs > 0 ? round(($cat->total / $totalJobs) * 100, 1) : 0,
                ];
            });

        // Application and Hiring Funnel
        $totalApps = JobApplication::count();
        $pendingApps = JobApplication::where('status', 'pending')->count();
        $interviewApps = JobApplication::where('status', 'interview')->count();
        $hiredApps = JobApplication::whereIn('status', ['accepted', 'hired'])->count();
        $issuedCerts = JobApplication::where('certificate_status', 'issued')->count();
        $hiringRate = $totalApps > 0 ? round(($hiredApps / $totalApps) * 100, 1) : 0;

        // Ratings & Feedback
        $avgRating = round(Review::avg('rating') ?: 5.0, 1);
        $totalReviews = Review::count();

        $analytics = [
            'users' => [
                'total' => User::count(),
                'students' => $totalStudents,
                'verified_students' => $verifiedStudents,
                'employers' => $totalEmployers,
                'verified_employers' => $verifiedEmployers,
                'households' => $totalHouseholds,
                'verified_households' => $verifiedHouseholds,
                'verification_rate' => $verificationRate,
            ],
            'jobs' => [
                'total' => $totalJobs,
                'active' => $activeJobs,
                'hidden' => $hiddenJobs,
                'closed' => $closedJobs,
                'categories' => $categoriesBreakdown,
            ],
            'funnel' => [
                'total_applications' => $totalApps,
                'pending' => $pendingApps,
                'interview' => $interviewApps,
                'hired' => $hiredApps,
                'hiring_rate' => $hiringRate,
                'certificates_issued' => $issuedCerts,
            ],
            'reviews' => [
                'average_rating' => $avgRating,
                'total' => $totalReviews,
            ],
        ];

        return view('admin.reports', compact('reports', 'incidentStats', 'analytics', 'tab', 'types'));
    }

    /**
     * Update the moderation status and notes of a report.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,investigating,resolved,dismissed',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $report = Report::findOrFail($id);
        $report->status = $request->input('status');
        $report->admin_notes = $request->input('admin_notes');

        if ($request->input('status') === 'resolved') {
            $report->resolved_at = now();
        }

        $report->save();

        return redirect()->back()->with('success', "Report #{$report->id} status updated to " . ucfirst($report->status) . ".");
    }

    /**
     * Delete a report.
     */
    public function destroy($id)
    {
        $report = Report::findOrFail($id);
        $reportId = $report->id;
        $report->delete();

        return redirect()->back()->with('success', "Report #{$reportId} has been deleted.");
    }

    /**
     * Poll recent reports for real-time admin notification bell & toast alert.
     */
    public function pollNotifications(Request $request)
    {
        $pendingCount = Report::whereIn('status', ['pending', 'investigating'])->count();

        $recentReports = Report::with([
            'reporter.studentProfile', 
            'reporter.employerProfile', 
            'reporter.householdProfile', 
            'job'
        ])
            ->whereIn('status', ['pending', 'investigating'])
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($r) {
                $reporterName = $r->reporter?->studentProfile?->student_name
                    ?? $r->reporter?->employerProfile?->company_name
                    ?? $r->reporter?->householdProfile?->household_name
                    ?? $r->reporter?->name
                    ?? 'Platform User';

                return [
                    'id' => $r->id,
                    'subject' => $r->subject ?: ucfirst(str_replace('_', ' ', $r->report_type)),
                    'report_type' => $r->report_type,
                    'reporter_name' => $reporterName,
                    'job_title' => $r->job?->title,
                    'status' => $r->status,
                    'created_at_human' => $r->created_at ? $r->created_at->diffForHumans() : 'just now',
                    'timestamp' => $r->created_at ? $r->created_at->timestamp : time(),
                ];
            });

        $latestId = Report::max('id') ?? 0;

        return response()->json([
            'status' => 'success',
            'pending_count' => $pendingCount,
            'latest_id' => $latestId,
            'reports' => $recentReports,
        ]);
    }
}
