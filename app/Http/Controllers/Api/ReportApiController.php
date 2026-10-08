<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportApiController extends Controller
{
    /**
     * Submit an incident or safety report.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reported_user_id' => 'nullable|exists:users,id',
            'job_id' => 'nullable|exists:available_jobs,id',
            'report_type' => 'required|in:scam,abuse,fake_job,underpaid,unsafe,no_show,other',
            'subject' => 'nullable|string|max:255',
            'description' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Check if already reported and pending/investigating
        $alreadyReported = Report::where('reporter_id', $request->user()->id)
            ->where(function ($q) use ($request) {
                if ($request->filled('job_id')) {
                    $q->where('job_id', $request->input('job_id'));
                }
                if ($request->filled('reported_user_id')) {
                    $q->orWhere('reported_user_id', $request->input('reported_user_id'));
                }
            })
            ->whereIn('status', ['pending', 'investigating'])
            ->exists();

        if ($alreadyReported) {
            return response()->json([
                'status' => 'already_reported',
                'message' => 'You have already reported this job or hirer. Our safety team is actively reviewing your submission.',
            ], 200);
        }

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $request->input('reported_user_id'),
            'job_id' => $request->input('job_id'),
            'report_type' => $request->input('report_type'),
            'subject' => $request->input('subject') ?: ucfirst(str_replace('_', ' ', $request->input('report_type'))),
            'description' => $request->input('description'),
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Report submitted successfully. Our safety team will review this incident.',
            'report' => $report,
        ], 201);
    }

    /**
     * Get reports submitted by the authenticated user.
     */
    public function myReports(Request $request)
    {
        $reports = Report::with(['job', 'reportedUser.studentProfile', 'reportedUser.employerProfile', 'reportedUser.householdProfile'])
            ->where('reporter_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function ($r) {
                $reportedName = $r->reportedUser?->employerProfile?->company_name
                    ?? $r->reportedUser?->employerProfile?->employer_name
                    ?? $r->reportedUser?->householdProfile?->household_name
                    ?? $r->reportedUser?->studentProfile?->student_name
                    ?? $r->job?->title
                    ?? 'Target Listing / User';

                return [
                    'id' => $r->id,
                    'reference_no' => 'REP-' . str_pad($r->id, 5, '0', STR_PAD_LEFT),
                    'report_type' => $r->report_type,
                    'subject' => $r->subject,
                    'description' => $r->description,
                    'status' => $r->status, // pending, investigating, resolved, dismissed
                    'admin_notes' => $r->admin_notes,
                    'job_id' => $r->job_id,
                    'job_title' => $r->job?->title,
                    'reported_name' => $reportedName,
                    'created_at' => $r->created_at ? $r->created_at->format('M d, Y · h:i A') : null,
                    'resolved_at' => $r->resolved_at ? $r->resolved_at->format('M d, Y · h:i A') : null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'reports' => $reports,
        ]);
    }
}
