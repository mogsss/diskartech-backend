<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletion;
use App\Support\PlainTextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const PROFILES = [
        'student' => ['studentProfile', ['student_name', 'student_school_name']],
        'employer' => ['employerProfile', ['hirer_name', 'employer_name']],
        'household' => ['householdProfile', ['household_name']],
    ];

    public function usersIndex(Request $request)
    {
        if (is_string($request->query('search'))) $request->merge(['search' => PlainTextInput::normalize($request->query('search'))]);
        $filters = $request->validate([
            'search' => ['bail', 'nullable', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (PlainTextInput::containsMarkup($value)) $fail('Search must contain plain text only.');
            }],
            'role' => ['nullable', Rule::in(['all', ...array_keys(self::PROFILES)])],
            'verification' => ['nullable', Rule::in(['all', 'verified', 'unverified'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = $filters['search'] ?? '';
        $role = $filters['role'] ?? 'all';
        $verification = $filters['verification'] ?? 'all';
        $base = User::whereIn('role', array_keys(self::PROFILES));
        $roleCounts = (clone $base)->selectRaw('role, COUNT(*) AS total')->groupBy('role')->pluck('total', 'role');
        $totalAccounts = $base->count();
        $verifiedAccounts = $this->filterVerification(clone $base, true)->count();
        $query = clone $base;
        if ($role !== 'all') $query->where('role', $role);
        if ($verification !== 'all') $this->filterVerification($query, $verification === 'verified');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereRaw("email LIKE ? ESCAPE '!'", [$pattern]);
                foreach (self::PROFILES as $type => [$relation, $fields]) {
                    $query->orWhere(function (Builder $query) use ($type, $relation, $fields, $pattern) {
                        $query->where('role', $type)->whereHas($relation, function (Builder $profile) use ($fields, $pattern) {
                            $profile->where(function (Builder $profile) use ($fields, $pattern) {
                                foreach ($fields as $field) $profile->orWhereRaw("{$field} LIKE ? ESCAPE '!'", [$pattern]);
                            });
                        });
                    });
                }
            });
        }
        $users = $query->with(['studentProfile', 'employerProfile', 'householdProfile'])->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();
        $users->getCollection()->transform(function (User $user) {
            $profile = $user->{self::PROFILES[$user->role][0]};
            $name = match ($user->role) {
                'student' => $profile?->student_name,
                'employer' => $profile?->hirer_name ?: $profile?->employer_name,
                'household' => $profile?->household_name,
            } ?: $user->email;
            $fields = [
                ['label' => 'Email address', 'value' => $user->email],
                ['label' => 'Contact number', 'value' => ($user->role === 'household' ? $profile?->cp_number : $profile?->contact_number) ?: 'Not provided'],
                ['label' => 'Address', 'value' => $profile?->detailed_address ?: ($profile?->location ?: 'Not provided')],
            ];
            if ($user->role === 'student') {
                foreach (['School' => 'student_school_name', 'Course' => 'course', 'Year level' => 'year_level'] as $label => $field) $fields[] = ['label' => $label, 'value' => $profile?->$field ?: 'Not provided'];
            } elseif ($user->role === 'employer') {
                foreach (['Business name' => 'employer_name', 'Business type' => 'business_type'] as $label => $field) $fields[] = ['label' => $label, 'value' => $profile?->$field ?: 'Not provided'];
            }
            $user->setAttribute('admin_details', [
                'id' => $user->id, 'name' => $name, 'role' => ucfirst($user->role),
                'verified' => (bool) $profile?->isVerified, 'hasProfile' => $profile !== null,
                'joined' => $user->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A').' PHT',
                'reference' => match ($user->role) { 'student' => $profile?->student_school_name, 'employer' => $profile?->employer_name, default => null },
                'fields' => $fields,
                'verificationUrl' => route('admin.verification', ['status' => 'all', 'role' => $user->role, 'search' => $user->email], false),
                'deletionUrl' => route('admin.users.destroy', $user->id, false),
            ]);
            return $user;
        });
        return view('admin.users', compact('users', 'totalAccounts', 'roleCounts', 'verifiedAccounts', 'search', 'role', 'verification'));
    }

    public function destroy(Request $request, int $id, AccountDeletion $deletion)
    {
        if (is_string($request->input('reason'))) $request->merge(['reason' => PlainTextInput::normalize($request->input('reason'))]);
        $data = $request->validate([
            'reason' => ['bail', 'required', 'string', 'min:5', 'max:1000', function ($attribute, $value, $fail) {
                if (PlainTextInput::containsMarkup($value)) $fail('The deletion reason must contain plain text only.');
            }],
            'confirmation' => ['required', 'string', Rule::in(['DELETE'])],
            'acknowledged' => ['accepted'],
            'current_password' => ['required', 'string', 'current_password:admin'],
        ]);

        DB::transaction(function () use ($request, $id, $data, $deletion) {
            // Resolve only mobile accounts under the same lock used by self-deletion.
            // A deleted account or a repeated request cannot create another audit.
            $user = User::whereIn('role', array_keys(self::PROFILES))->lockForUpdate()->findOrFail($id);
            DB::table('admin_user_deletions')->insert([
                'admin_id' => $request->user('admin')->id,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'reason' => $data['reason'],
                'created_at' => now(),
            ]);
            $deletion->delete($user, byAdmin: true);
        });

        $message = 'Account deleted. Access has been revoked and personal profile details have been removed.';
        $request->session()->flash('success', $message);
        if ($request->expectsJson()) return response()->json(['message' => $message]);
        return to_route('admin.users');
    }

    private function filterVerification(Builder $query, bool $verified): Builder
    {
        return $query->where(function (Builder $query) use ($verified) {
            foreach (self::PROFILES as $type => [$relation]) {
                $query->orWhere(function (Builder $query) use ($type, $relation, $verified) {
                    $query->where('role', $type)->where(function (Builder $query) use ($relation, $verified) {
                        if (!$verified) $query->whereDoesntHave($relation);
                        $query->orWhereHas($relation, function (Builder $profile) use ($verified) {
                            if ($verified) $profile->where('isVerified', true);
                            else $profile->where(fn (Builder $profile) => $profile->where('isVerified', false)->orWhereNull('isVerified'));
                        });
                    });
                });
            }
        });
    }
}
