<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AccountController extends Controller
{
    private function accountAccess(Request $request): array
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['student', 'employer', 'household'], true), 403);
        $token = $user->currentAccessToken();
        // Inspect the literal marker. Wildcard Sanctum tokens can() match any ability.
        $googleSession = $token instanceof PersonalAccessToken && in_array('auth:google', $token->abilities ?? [], true);
        $googleRegistration = (bool) $user->registered_with_google;
        return [
            'registered_with_google' => $googleRegistration,
            'authentication_method' => $googleSession ? 'google' : 'password',
            'can_change_password' => !$googleRegistration && !$googleSession,
        ];
    }

    public function show(Request $request)
    {
        return response()->json(['status' => 'success', 'account' => $this->accountAccess($request)])
            ->header('Cache-Control', 'no-store');
    }

    public function updatePassword(Request $request)
    {
        if (!$this->accountAccess($request)['can_change_password']) {
            return response()->json(['status' => 'error', 'code' => 'google_authentication', 'message' => 'Password changes are unavailable for Google registration or Google sign-in. Manage your Google password in your Google Account.'], 403);
        }

        // Passwords are validated as credentials, never trimmed or sanitized into another value.
        $data = $request->validate([
            'current_password' => ['bail', 'required', 'string', 'max:4096', 'not_regex:/[\x00-\x1F\x7F]/'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'max:72', 'confirmed',
                'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/',
                'not_regex:/[\x00-\x1F\x7F]/',
                function ($attribute, $value, $fail) {
                    // Bcrypt only considers the first 72 bytes of a password.
                    if (strlen($value) > 72) $fail('Please choose a shorter password.');
                },
            ],
            'password_confirmation' => ['required', 'string', 'max:72'],
        ], [
            'password.regex' => 'Use at least one uppercase letter, one number, and one special character (!@#$%^&*(),.?":{}|<>).',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.not_regex' => 'The password contains unsupported characters.',
            'current_password.not_regex' => 'The current password contains unsupported characters.',
        ]);

        DB::transaction(function () use ($request, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current DiskarTech password is incorrect.']);
            }
            if (Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'Choose a different password from your current password.']);
            }

            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
            $currentToken = $request->user()->currentAccessToken();
            if ($currentToken instanceof PersonalAccessToken) {
                $user->tokens()->whereKeyNot($currentToken->getKey())->delete();
            } else {
                $user->tokens()->delete();
            }
        });

        return response()->json(['status' => 'success', 'message' => 'Your DiskarTech password has been updated.'])
            ->header('Cache-Control', 'no-store');
    }
}
