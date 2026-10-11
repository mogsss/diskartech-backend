<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminSession;
use App\Support\PlainTextInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Ipakita ang login form (kung gagamit ka ng Blade view)
    public function showLoginForm()
    {
        return view('admin.auth.login'); // O kaya ay kung saan nakalagay ang admin login view mo
    }

    // Proseso ng pag-login
    public function login(Request $request, AdminSession $sessions)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => PlainTextInput::normalize($request->input('email'))]);
        }
        $credentials = $request->validate([
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $token = Str::random(64);
        $previousToken = $sessions->browserToken($request);
        $blocked = false;
        // The callback runs only after the credentials have been verified.
        if (Auth::guard('admin')->attemptWhen($credentials, function ($admin) use ($sessions, $token, $previousToken, &$blocked) {
            $blocked = ! $sessions->claim($admin, $token, $previousToken);
            return ! $blocked;
        })) {
            $request->session()->regenerate();
            // This token survives session ID changes, including password updates.
            $request->session()->put(AdminSession::SESSION_KEY, $token);

            return $sessions->rememberBrowser(redirect()->intended('/admin/dashboard'), $token);
        }

        return back()->withErrors([
            'email' => $blocked
                ? 'This admin account is already signed in on another browser or device. Sign out there first, or wait for that session to expire.'
                : 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    // Proseso ng pag-logout
    public function logout(Request $request, AdminSession $sessions)
    {
        $token = $sessions->browserToken($request);
        if (is_string($token)) $sessions->release($request->user('admin'), $token);
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $sessions->forgetBrowser(redirect('/admin/login'));
    }
    
}
