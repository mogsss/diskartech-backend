<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Ipakita ang login form (kung gagamit ka ng Blade view)
    public function showLoginForm()
    {
        return view('admin.auth.login'); // O kaya ay kung saan nakalagay ang admin login view mo
    }

    // Proseso ng pag-login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Gagamitin natin ang hiwalay na guard para sa admin
        if (Auth::guard('admin')->attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    // Proseso ng pag-logout
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
    
}