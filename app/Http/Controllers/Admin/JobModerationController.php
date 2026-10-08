<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jobs;
use App\Models\JobApplication;
use Illuminate\Http\Request;

class JobModerationController extends Controller
{
    /**
     * Display a listing of jobs for admin moderation.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $category = $request->input('category');

        $query = Jobs::with(['employer', 'household', 'user', 'applications.student']);

        // Search across title, description, category, poster names, and email
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($sub) use ($search) {
                        $sub->where('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('employer', function ($sub) use ($search) {
                        $sub->where('employer_name', 'like', "%{$search}%")
                            ->orWhere('hirer_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('household', function ($sub) use ($search) {
                        $sub->where('household_name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status (active = Live, hidden = Hidden, closed = Closed)
        if ($status && in_array($status, ['active', 'hidden', 'closed'])) {
            $query->where('status', $status);
        }

        // Filter by category
        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate(10)->appends($request->all());

        // Header statistics
        $stats = [
            'total' => Jobs::count(),
            'live' => Jobs::where('status', 'active')->count(),
            'hidden' => Jobs::where('status', 'hidden')->count(),
            'applications' => JobApplication::count(),
        ];

        // Categories available in database
        $categories = Jobs::select('category')->whereNotNull('category')->distinct()->pluck('category');

        return view('admin.job-moderation', compact('jobs', 'stats', 'categories'));
    }

    /**
     * Toggle status between 'active' (Live) and 'hidden' (Hidden).
     */
    public function toggleStatus(Request $request, $id)
    {
        $job = Jobs::findOrFail($id);

        if ($job->status === 'active') {
            $job->status = 'hidden';
            $message = "Job '{$job->title}' has been hidden from students.";
        } else {
            $job->status = 'active';
            $message = "Job '{$job->title}' is now live and published.";
        }

        $job->save();

        return redirect()->back()->with('success', $message);
    }

    /**
     * Explicitly update status (active, hidden, closed).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,hidden,closed',
        ]);

        $job = Jobs::findOrFail($id);
        $job->status = $request->input('status');
        $job->save();

        return redirect()->back()->with('success', "Status for '{$job->title}' changed to " . ucfirst($job->status) . ".");
    }

    /**
     * Delete a job post permanently.
     */
    public function destroy($id)
    {
        $job = Jobs::findOrFail($id);
        $title = $job->title;

        // Cascade delete related applications
        $job->applications()->delete();
        $job->delete();

        return redirect()->back()->with('success', "Job '{$title}' has been permanently deleted.");
    }
}
