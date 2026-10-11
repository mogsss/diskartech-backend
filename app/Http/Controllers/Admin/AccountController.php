<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\PlainTextInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    private const PASSWORD_CHECKS = [
        ['label' => 'At least one uppercase letter', 'pattern' => '[A-Z]'],
        ['label' => 'At least one number', 'pattern' => '[0-9]'],
        ['label' => 'At least one special character (!@#$%^&*(),.?":{}|<>)', 'pattern' => '[!@#$%^&*(),.?":{}|<>]'],
    ];

    public function edit(Request $request)
    {
        return view('admin.account', ['admin' => $request->user('admin'), 'passwordChecks' => self::PASSWORD_CHECKS]);
    }

    public function checkEmail(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => ['bail', 'required', 'string', 'email:rfc', 'max:255']]);
        $available = !Admin::whereRaw('LOWER(email) = ?', [$data['email']])->whereKeyNot($request->user('admin')->id)->exists();
        return response()->json(['available' => $available, 'message' => $available ? 'This email address is available.' : 'This email address is already used by another admin.'])
            ->header('Cache-Control', 'no-store');
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) $request->merge(['email' => strtolower(PlainTextInput::normalize($request->input('email')))]);
    }

    public function update(Request $request)
    {
        $admin = $request->user('admin');
        foreach (['name', 'email'] as $field) {
            if (is_string($request->input($field))) $request->merge([$field => PlainTextInput::normalize($request->input($field))]);
        }
        $this->normalizeEmail($request);
        $changingEmail = $request->input('email') !== strtolower($admin->email);
        $data = $request->validateWithBag('profile', [
            'name' => ['bail', 'required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (PlainTextInput::containsMarkup($value)) $fail('Your name must contain plain text only.');
            }],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', function ($attribute, $value, $fail) use ($admin) {
                if (Admin::whereRaw('LOWER(email) = ?', [$value])->whereKeyNot($admin->id)->exists()) $fail('This email address is already used by another admin.');
            }],
            'current_password' => $changingEmail ? ['required', 'string', 'current_password:admin'] : ['nullable', 'string'],
        ]);
        // Never accept a target account ID or sanitize credentials.
        $admin->fill(['name' => $data['name'], 'email' => $data['email']])->save();
        return to_route('admin.account')->with('success', 'Your account details have been updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string', 'current_password:admin'],
            'password' => ['bail', 'required', 'string', Password::min(8), 'max:255', 'confirmed', 'different:current_password', function ($attribute, $value, $fail) {
                foreach (self::PASSWORD_CHECKS as $check) {
                    if (!preg_match('~'.$check['pattern'].'~', $value)) $fail('Password requirement: '.$check['label'].'.');
                }
            }],
        ]);
        $request->user('admin')->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
        $request->session()->regenerate();
        return to_route('admin.account')->with('success', 'Your password has been updated.');
    }
}
