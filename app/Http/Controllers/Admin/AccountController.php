<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PlainTextInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.account', ['admin' => $request->user('admin')]);
    }

    public function update(Request $request)
    {
        $admin = $request->user('admin');
        foreach (['name', 'email'] as $field) {
            if (is_string($request->input($field))) $request->merge([$field => PlainTextInput::normalize($request->input($field))]);
        }
        if (is_string($request->input('email'))) $request->merge(['email' => strtolower($request->input('email'))]);
        $changingEmail = $request->input('email') !== $admin->email;
        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (PlainTextInput::containsMarkup($value)) $fail('Your name must contain plain text only.');
            }],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
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
            'password' => ['required', 'string', Password::min(8), 'max:255', 'confirmed', 'different:current_password'],
        ]);
        $request->user('admin')->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
        $request->session()->regenerate();
        return to_route('admin.account')->with('success', 'Your password has been updated.');
    }
}
