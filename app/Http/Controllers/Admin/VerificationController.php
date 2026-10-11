<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PlainTextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{
    private const PROFILES = [
        'student' => ['studentProfile', 'student_name', 'student_school_name', ['school_id', 'coe', 'student_resume']],
        'employer' => ['employerProfile', 'hirer_name', 'employer_name', ['valid_id_path', 'employer_certificate_path']],
        'household' => ['householdProfile', 'household_name', 'household_name', ['valid_id_path']],
    ];

    public function verificationIndex(Request $request)
    {
        if (is_string($request->query('search'))) {
            $request->merge(['search' => PlainTextInput::normalize($request->query('search'))]);
        }
        $filters = $request->validate([
            'search' => ['bail', 'nullable', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (PlainTextInput::containsMarkup($value)) $fail('Search must contain plain text only.');
            }],
            'role' => ['nullable', Rule::in(['all', ...array_keys(self::PROFILES)])],
            'status' => ['nullable', Rule::in(['all', 'pending', 'verified', 'rejected', 'incomplete'])],
        ]);
        $search = $filters['search'] ?? '';
        $role = $filters['role'] ?? 'all';
        $status = $filters['status'] ?? 'pending';
        $base = User::whereIn('role', array_keys(self::PROFILES));
        $statusCounts = [];
        foreach (['pending', 'verified', 'rejected', 'incomplete'] as $state) {
            $statusCounts[$state] = $this->filterStatus(clone $base, $state)->count();
        }
        $totalAccounts = $base->count();
        $query = clone $base;
        if ($role !== 'all') $query->where('role', $role);
        if ($status !== 'all') $this->filterStatus($query, $status);
        if ($search !== '') {
            // Bound values; SQL wildcard characters are treated as literal search text.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereRaw("email LIKE ? ESCAPE '!'", [$pattern]);
                foreach (self::PROFILES as $type => [$relation, $name, $reference]) {
                    $query->orWhere(function (Builder $query) use ($type, $relation, $name, $reference, $pattern) {
                        $query->where('role', $type)->whereHas($relation, function (Builder $profile) use ($name, $reference, $pattern) {
                            $profile->whereRaw("{$name} LIKE ? ESCAPE '!'", [$pattern])
                                ->orWhereRaw("{$reference} LIKE ? ESCAPE '!'", [$pattern]);
                        });
                    });
                }
            });
        }
        $users = $query->with(['studentProfile', 'employerProfile', 'householdProfile'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.verification', compact('users', 'statusCounts', 'totalAccounts', 'search', 'role', 'status'));
    }

    private function filterStatus(Builder $query, string $status): Builder
    {
        return $query->where(function (Builder $accounts) use ($status) {
            foreach (self::PROFILES as $role => [$relation, , , $documents]) {
                $accounts->orWhere(function (Builder $account) use ($role, $relation, $documents, $status) {
                    $account->where('role', $role)->where(function (Builder $account) use ($relation, $documents, $status) {
                        if ($status === 'incomplete') $account->whereDoesntHave($relation);
                        $account->orWhereHas($relation, function (Builder $profile) use ($documents, $status) {
                            if ($status === 'verified') {
                                $profile->where('isVerified', true);
                                return;
                            }
                            $profile->where(fn (Builder $profile) => $profile->where('isVerified', false)->orWhereNull('isVerified'));
                            if ($status === 'rejected') {
                                $profile->whereNotNull('rejection_reason')->where('rejection_reason', '!=', '');
                                return;
                            }
                            $profile->where(fn (Builder $profile) => $profile->whereNull('rejection_reason')->orWhere('rejection_reason', ''));
                            if ($status === 'pending') {
                                foreach ($documents as $field) $profile->whereNotNull($field)->where($field, '!=', '');
                            } else {
                                $profile->where(function (Builder $profile) use ($documents) {
                                    foreach ($documents as $field) $profile->orWhereNull($field)->orWhere($field, '');
                                });
                            }
                        });
                    });
                });
            }
        });
    }

    private function reviewedProfile(Request $request, $id): array
    {
        $request->validate(['role' => ['required', Rule::in(array_keys(self::PROFILES))]]);
        $user = User::findOrFail($id);
        abort_unless($request->input('role') === $user->role, 422, 'The account role does not match this verification.');
        return [$user->{self::PROFILES[$user->role][0]}, self::PROFILES[$user->role][3]];
    }

    public function rejectVerification(Request $request, $id)
    {
        if (is_string($request->input('rejection_reason'))) {
            $request->merge(['rejection_reason' => PlainTextInput::normalize($request->input('rejection_reason'))]);
        }
        $request->validate(['rejection_reason' => ['bail', 'required', 'string', 'max:255', function ($attribute, $value, $fail) {
            if (PlainTextInput::containsMarkup($value)) $fail('The rejection reason must contain plain text only.');
        }]]);
        [$profile, $documents] = $this->reviewedProfile($request, $id);
        if (!$profile) return back()->with('error', 'Applicant profile not found.');
        if ((bool) $profile->isVerified) return back()->with('error', 'This account is already verified.');
        foreach ($documents as $field) {
            if (blank($profile->$field)) return back()->with('error', 'Upload all required documents before reviewing this account.');
        }
        $profile->isVerified = false;
        $profile->rejection_reason = $request->input('rejection_reason');
        $profile->save();
        return back()->with('success', 'Application rejected successfully.');
    }

    public function approveVerification(Request $request, $id)
    {
        [$profile, $documents] = $this->reviewedProfile($request, $id);
        if (!$profile) return back()->with('error', 'Applicant profile not found.');
        foreach ($documents as $field) {
            if (blank($profile->$field)) return back()->with('error', 'Upload all required documents before approving this account.');
        }
        if ((bool) $profile->isVerified) return back()->with('success', 'This account is already verified.');
        $profile->isVerified = true;
        $profile->rejection_reason = null;
        $profile->save();
        return back()->with('success', 'Verification approved successfully.');
    }
}
