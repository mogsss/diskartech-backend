<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Jobs;
use App\Support\PlainTextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobModerationController extends Controller
{
    public function index(Request $request)
    {
        foreach (['search', 'category'] as $field) {
            if (is_string($request->query($field))) $request->merge([$field => PlainTextInput::normalize($request->query($field))]);
        }
        $plainText = function ($attribute, $value, $fail) {
            if (PlainTextInput::containsMarkup($value)) $fail('Filters must contain plain text only.');
        };
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120', $plainText],
            'status' => ['nullable', Rule::in(['all', 'active', 'hidden', 'closed'])],
            'category' => ['nullable', 'string', 'max:255', $plainText],
        ]);
        $search = $filters['search'] ?? '';
        $status = $filters['status'] ?? 'all';
        $category = $filters['category'] ?? 'all';
        $query = Jobs::with(['employer', 'household', 'user'])->withCount('applications');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern) {
                foreach (['title', 'description', 'category'] as $field) $query->orWhereRaw("{$field} LIKE ? ESCAPE '!'", [$pattern]);
                $query->orWhereHas('user', function (Builder $user) use ($pattern) {
                    $user->whereRaw("email LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhere(function (Builder $user) use ($pattern) {
                            $user->where('role', 'employer')->whereHas('employerProfile', function (Builder $profile) use ($pattern) {
                                $profile->whereRaw("employer_name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("hirer_name LIKE ? ESCAPE '!'", [$pattern]);
                            });
                        })->orWhere(function (Builder $user) use ($pattern) {
                            $user->where('role', 'household')->whereHas('householdProfile', fn (Builder $profile) => $profile->whereRaw("household_name LIKE ? ESCAPE '!'", [$pattern]));
                        });
                });
            });
        }
        if ($status !== 'all') $query->where('status', $status);
        if ($category !== 'all') $query->where('category', $category);
        $jobs = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();
        $jobs->getCollection()->transform(function (Jobs $job) {
            $job->setAttribute('moderation_poster', $this->poster($job));
            return $job;
        });
        $stats = [
            'total' => Jobs::count(),
            'live' => Jobs::where('status', 'active')->count(),
            'hidden' => Jobs::where('status', 'hidden')->count(),
            'closed' => Jobs::where('status', 'closed')->count(),
            'applications' => JobApplication::count(),
        ];
        $categories = Jobs::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');
        return view('admin.job-moderation', compact('jobs', 'stats', 'categories', 'search', 'status', 'category'));
    }

    public function show(Request $request, $id)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $job = Jobs::with(['employer', 'household', 'user'])->withCount('applications')->findOrFail($id);
        $applications = $job->applications()->with('student')->orderByDesc('created_at')->orderByDesc('id')->paginate(10);
        return response()->json([
            'job' => [
                'id' => $job->id, 'title' => $job->title, 'description' => $job->description,
                'category' => $job->category ?: 'Uncategorized', 'status' => $job->status,
                'rate' => '₱'.number_format((float) $job->salary, 2).' / '.($job->salary_type === 'hour' ? 'hr' : 'day'),
                'available_days' => $this->stringList($job->available_days),
                'time_slot' => $job->time_slot ?: 'Not specified',
                'requirements' => $this->stringList($job->requirements), 'skills' => $this->stringList($job->skills),
                'posted_at' => $job->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A').' PHT',
                'poster' => $this->poster($job), 'applications_count' => $job->applications_count,
                'status_url' => route('admin.job-moderation.status', $job->id),
                'delete_url' => route('admin.job-moderation.destroy', $job->id),
            ],
            'applicants' => $applications->getCollection()->map(fn ($application) => [
                'id' => $application->id, 'name' => $application->student?->student_name ?: 'Student no longer available',
                'school' => $application->student?->student_school_name ?: 'School not provided',
                'course' => $application->student?->course ?: 'Course not provided',
                'status' => ucfirst(strtolower($application->status ?? 'pending')),
                'date' => $application->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A').' PHT',
            ]),
            'pagination' => ['current' => $applications->currentPage(), 'last' => $applications->lastPage(),
                'total' => $applications->total(), 'previous' => $applications->previousPageUrl(), 'next' => $applications->nextPageUrl()],
        ]);
    }

    private function poster(Jobs $job): array
    {
        $role = $job->user?->role;
        $profile = match ($role) { 'employer' => $job->employer, 'household' => $job->household, default => null };
        $name = match ($role) {
            'employer' => $profile?->employer_name ?: $profile?->hirer_name,
            'household' => $profile?->household_name,
            default => null,
        };
        return [
            'name' => $name ?: ($job->user?->email ?: 'Account no longer available'),
            'role' => in_array($role, ['employer', 'household']) ? ucfirst($role) : 'Unavailable',
            'email' => $job->user?->email ?: 'Not provided',
            'phone' => ($role === 'household' ? $profile?->cp_number : $profile?->contact_number) ?: 'Not provided',
            'location' => $profile?->detailed_address ?: ($profile?->location ?: 'Not provided'),
            'verified' => (bool) $profile?->isVerified,
        ];
    }

    private function stringList($value): array
    {
        // Older posts may contain JSON serialized twice inside an array-cast column.
        for ($depth = 0; $depth < 2 && is_string($value); $depth++) $value = json_decode($value, true);
        if (!is_array($value)) return [];
        return array_values(array_unique(array_filter($value, fn ($item) => is_string($item) && trim($item) !== '')));
    }

    public function toggleStatus(Request $request, $id)
    {
        $job = Jobs::findOrFail($id);
        if (!in_array($job->status, ['active', 'hidden'])) {
            return back()->with('error', 'This job is closed. Use Reopen job to publish it again.');
        }
        $job->status = $job->status === 'active' ? 'hidden' : 'active';
        $job->save();
        return back()->with('success', $job->status === 'active' ? "Job '{$job->title}' is now live." : "Job '{$job->title}' has been hidden from students.");
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate(['status' => ['required', 'string', Rule::in(['active', 'hidden', 'closed'])]]);
        $job = Jobs::findOrFail($id);
        $job->update(['status' => $validated['status']]);
        $label = $job->status === 'active' ? 'live' : $job->status;
        return back()->with('success', "Job '{$job->title}' is now {$label}.");
    }

    public function destroy($id)
    {
        $job = Jobs::findOrFail($id);
        $title = $job->title;
        DB::transaction(function () use ($job) {
            $job->applications()->delete();
            $job->delete();
        });
        return back()->with('success', "Job '{$title}' and its applications have been permanently deleted.");
    }
}
