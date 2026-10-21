<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\Jobs;
use App\Support\PlainTextInput;
use App\Services\UploadStorage;
use App\Services\EmployerJobQuota;
use Illuminate\Database\Eloquent\Builder;

class EmployerController extends Controller
{
    private function hirerId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['employer', 'household'], true), 403, 'Only hirer accounts can manage applicants.');
        return (int) $user->id;
    }

    private function applicationsFor(int $userId): Builder
    {
        return JobApplication::whereHas('job', fn (Builder $query) => $query->where('user_id', $userId));
    }

    public function addAvatar(Request $request)
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();
        $file = $request->file('profile_picture');
        abort_unless(in_array($user->role, ['employer', 'household'], true), 403);
        $storage = app(UploadStorage::class);

        // 👇 Tukuyin ang folder depende sa role ng user
        if ($user->role === 'household') {
            $path = $storage->store($file, 'household/avatar', true);

            // Isinave na relative path lang (halimbawa: household/avatar/filename.jpg)
            \App\Models\Household::where('user_id', $user->id)->update([
                'avatar' => $path
            ]);
        } else {
            // Default para sa employer
            $path = $storage->store($file, 'employers/avatar', true);

            // Isinave na relative path lang (halimbawa: employers/avatar/filename.jpg)
            \App\Models\Employer::where('user_id', $user->id)->update([
                'avatar' => $path
            ]);
        }

        // Buong URL pa rin ang ibabato sa JSON response para sa mobile app
        $url = $storage->url($path);

        return response()->json([
            'status' => 'success',
            'message' => 'Avatar updated successfully',
            'profile_picture' => $url,
            'avatar' => $url,
            'file_path' => $path,
        ], 200);
    }
    // Ginagamit ito para sa dashboard (kasama na ang applications at student info)
    public function myJobListings(Request $request)
    {
        $userId = $this->hirerId($request);

        $jobs = Jobs::where('user_id', $userId)
            ->with(['applications.student']) // Isinama para makuha ang mga nag-apply
            ->withCount('applications')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'jobs' => $jobs,
            'job_quota' => app(EmployerJobQuota::class)->usage($request->user()),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function getJobApplicants(Request $request, $jobId)
    {
        $userId = $this->hirerId($request);
        $job = Jobs::whereKey($jobId)->where('user_id', $userId)->firstOrFail();
        $applications = $job->applications()->with(['student', 'job'])->get();

        return response()->json([
            'status' => 'success',
            'applicants' => $applications
        ])->header('Cache-Control', 'no-store, private');
    }

    public function getAllApplicants(Request $request)
    {
        $userId = $this->hirerId($request);

        $applications = $this->applicationsFor($userId)->with(['student', 'job'])->get();

        return response()->json([
            'status' => 'success',
            'applicants' => $applications
        ])->header('Cache-Control', 'no-store, private');
    }

    public function getApplicationDetails(Request $request, $applicationId)
    {
        $userId = $this->hirerId($request);
        $query = $this->applicationsFor($userId)->with([
            'student', 'job',
            'job.employer:id,user_id,location,detailed_address',
            'job.household:id,user_id,location,detailed_address',
        ])
            ->whereKey($applicationId);
        $application = $query->first();

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }
        if ($application->status === 'pending') {
            // Do not overwrite a hiring decision made while the details were loading.
            $this->applicationsFor($userId)->whereKey($applicationId)->where('status', 'pending')->update(['status' => 'viewed']);
            $application = $query->first();
            abort_unless($application, 404, 'Application not found');
        }

        return response()->json([
            'status' => 'success',
            'application' => $application
        ])->header('Cache-Control', 'no-store, private');
    }

    public function updateApplicationStatus(Request $request, $applicationId)
    {
        foreach (['start_date', 'end_date', 'work_schedule', 'agreed_rate', 'special_instructions', 'contract_terms', 'termination_reason', 'interview_location'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => PlainTextInput::normalize($request->input($field))]);
            }
        }
        $plainText = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && PlainTextInput::containsMarkup($value)) {
                $fail('The :attribute must contain plain text without HTML or script tags.');
            }
        };
        $startDateRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if (!is_string($value) || !$this->parseContractDate($value)) {
                $fail('The :attribute must be a valid date (YYYY-MM-DD or Month D, YYYY).');
            }
        };
        $endDateRule = function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if (in_array($value, ['Until Project Completion', 'Indefinite / Project-based'], true)) return;
            $end = is_string($value) ? $this->parseContractDate($value) : null;
            if (!$end) {
                $fail('The :attribute must be a valid date or a project-based end date.');
                return;
            }
            $startValue = $request->input('start_date');
            $start = is_string($startValue) ? $this->parseContractDate($startValue) : null;
            if ($start && $end < $start) $fail('The end date cannot be earlier than the start date.');
        };
        if (is_string($request->input('interview_time'))) {
            $request->merge(['interview_time' => $this->normalizeInterviewTime($request->input('interview_time'))]);
        }
        $request->validate([
            'status' => 'required|in:accepted,rejected,interview,cancelled,terminated,completed',
            'interview_type' => 'required_if:status,interview|in:walk-in,online',
            'interview_date' => 'required_if:status,interview|nullable|date',
            'interview_time' => 'required_if:status,interview|nullable|date_format:h:i A',
            'interview_location' => ['bail', 'required_if:interview_type,walk-in', 'nullable', 'string', 'max:2000', $plainText],
            // Additional fields when hiring is confirmed
            'start_date' => ['bail', 'sometimes', 'required', 'string', 'max:255', $startDateRule],
            'end_date' => ['bail', 'sometimes', 'nullable', 'string', 'max:255', $endDateRule],
            'work_schedule' => ['bail', 'sometimes', 'required', 'string', 'max:255', $plainText],
            'agreed_rate' => ['bail', 'sometimes', 'required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'special_instructions' => ['bail', 'sometimes', 'nullable', 'string', 'max:2000', $plainText],
            'contract_terms' => ['bail', 'sometimes', 'required', 'string', 'max:2000', $plainText],
            // Field when contract is terminated
            'termination_reason' => ['bail', 'sometimes', 'nullable', 'string', 'max:1000', $plainText],
        ]);

        $application = JobApplication::where('id', $applicationId)->first();

        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }

        abort_unless(in_array($request->user()->role, ['employer', 'household'], true)
            && (int) $application->job?->user_id === (int) $request->user()->id,
            403, 'You cannot update this application.');

        if ($request->status === 'accepted') {
            // Partial API updates must also respect dates already stored on the contract.
            $start = $this->parseContractDate((string) $request->input('start_date', $application->start_date));
            $end = $this->parseContractDate((string) $request->input('end_date', $application->end_date));
            if ($start && $end && $end < $start) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'end_date' => 'The end date cannot be earlier than the start date.',
                ]);
            }
        }

        $application->status = $request->status;
        // If the application is being accepted, capture hiring details
        if ($request->status === 'accepted') {
            if ($request->has('start_date')) {
                $application->start_date = $request->start_date;
            }
            if ($request->has('end_date')) {
                $application->end_date = $request->end_date;
            }
            if ($request->has('work_schedule')) {
                $application->work_schedule = $request->work_schedule;
            }
            if ($request->has('agreed_rate')) {
                $application->agreed_rate = $request->agreed_rate;
            }
            if ($request->has('special_instructions')) {
                $application->special_instructions = $request->special_instructions;
            }
            if ($request->has('contract_terms')) {
                $application->contract_terms = $request->contract_terms;
            }
            // Record the timestamp when the hire is confirmed
            $application->hired_at = now();
        }

        // If the application / contract is being terminated
        if ($request->status === 'terminated') {
            $application->terminated_at = now();
            if ($request->has('termination_reason')) {
                $application->termination_reason = $request->termination_reason;
            }
        }

        if ($request->has('interview_date')) {
            $application->interview_date = $request->interview_date;
        }
        if ($request->has('interview_time')) {
            $application->interview_time = $request->interview_time;
        }
        if ($request->has('interview_location')) {
            $application->interview_location = $request->interview_location;
        }
        if ($request->has('interview_type')) {
            $application->interview_type = $request->interview_type;
            if ($request->interview_type === 'online') {
                $application->interview_location = 'DiskarTech video call';
            }
        }

        // Only a new/changed interview schedule reopens an ended interview.
        if ($request->status === 'interview' && $application->isDirty(['status', 'interview_date', 'interview_time', 'interview_type'])) {
            $application->interview_ended_at = null;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($application, $request) {
            if ($request->status === 'accepted' && \Illuminate\Support\Facades\Schema::hasColumn('job_applications', 'work_schedule_slots')) {
                app(\App\Services\StudentScheduleMatching::class)->assertCanHire($application);
            }
            $application->save();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Application status updated successfully',
            'application' => $application
        ]);
    }
    private function parseContractDate(string $value): ?\DateTimeImmutable
    {
        foreach (['F j, Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) return $date;
        }
        return null;
    }
    private function normalizeInterviewTime(string $value): string
    {
        // Mobile locale formatters can emit NBSP, narrow NBSP or direction marks.
        $text = preg_replace('/[\x{200E}\x{200F}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value);
        $text = strtoupper(trim(preg_replace('/[\p{Z}\s]+/u', ' ', $text ?? $value) ?? $value));
        if (preg_match('/^(0?[1-9]|1[0-2]):([0-5]\d)(?::00)?\s*([AP]M)$/', $text, $parts)) {
            return sprintf('%02d:%s %s', (int) $parts[1], $parts[2], $parts[3]);
        }
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::00)?$/', $text, $parts)) {
            $hour = (int) $parts[1];
            return sprintf('%02d:%s %s', $hour % 12 ?: 12, $parts[2], $hour >= 12 ? 'PM' : 'AM');
        }

        return $value; // Leave invalid values for the existing validation to reject.
    }

    public function deleteApplication(Request $request, $applicationId)
    {
        $userId = $this->hirerId($request);
        $application = $this->applicationsFor($userId)->whereKey($applicationId)->first();
        if (!$application) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application not found'
            ], 404);
        }
        $application->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Application deleted successfully'
        ], 200);
    }

    public function postedJobs(Request $request)
    {
        return $this->myJobListings($request);
    }

    public function jobQuota(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['employer', 'household'], true), 403);
        return response()->json(['job_quota' => app(EmployerJobQuota::class)->usage($request->user())])->header('Cache-Control', 'no-store, private');
    }

    public function updateJobStatus(Request $request, $jobId)
    {
        $request->validate([
            'status' => 'required|string|in:active,closed,inactive'
        ]);

        $userId = $request->user()->id;
        $job = \App\Models\Jobs::where('id', $jobId)->where('user_id', $userId)->first();

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'Job not found or unauthorized'
            ], 404);
        }

        $job = app(EmployerJobQuota::class)->changeStatus($job, strtolower($request->status));

        return response()->json([
            'status' => 'success',
            'message' => 'Job status updated successfully',
            'job' => $job
        ]);
    }
}
