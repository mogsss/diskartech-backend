<?php

namespace App\Http\Middleware;

use App\Support\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSingleAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        $sessions = app(AdminSession::class);
        $token = $sessions->browserToken($request);

        if ($token === null && ! $request->session()->has(AdminSession::SESSION_KEY)) {
            // Adopt existing logins after deployment: the first active browser
            // claims the account; any other existing browser is signed out.
            $token = Str::random(64);
            $allowed = $sessions->claim($admin, $token);
        } else {
            $allowed = is_string($token) && $sessions->refresh($admin, $token);
        }

        if (! $allowed) {
            if (is_string($token)) $sessions->release($admin, $token);
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $message = 'Your admin session has expired or this account is signed in on another browser. Please sign in again.';

            if ($request->expectsJson()) return $sessions->forgetBrowser(response()->json(['message' => $message], 401));

            return $sessions->forgetBrowser(to_route('admin.login')->withErrors(['email' => $message]));
        }

        $request->session()->put(AdminSession::SESSION_KEY, $token);
        $response = $next($request);
        // Logout invalidates the session inside the controller. Do not restore
        // its ownership cookie while unwinding this middleware.
        if (! Auth::guard('admin')->check() || ! $request->session()->has(AdminSession::SESSION_KEY)) {
            return $sessions->forgetBrowser($response);
        }
        return $sessions->rememberBrowser($response, $token);
    }
}
