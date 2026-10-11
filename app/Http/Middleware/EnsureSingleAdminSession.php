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
        $token = $request->session()->get(AdminSession::SESSION_KEY);

        if ($token === null) {
            // Adopt existing logins after deployment: the first active browser
            // claims the account; any other existing browser is signed out.
            $token = Str::random(64);
            $allowed = $sessions->claim($admin, $token);
            if ($allowed) $request->session()->put(AdminSession::SESSION_KEY, $token);
        } else {
            $allowed = is_string($token) && $sessions->refresh($admin, $token);
        }

        if (! $allowed) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $message = 'Your admin session has expired or this account is signed in on another browser. Please sign in again.';

            if ($request->expectsJson()) return response()->json(['message' => $message], 401);

            return to_route('admin.login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
