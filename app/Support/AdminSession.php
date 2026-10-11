<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class AdminSession
{
    public const SESSION_KEY = 'admin_login_token';
    public const COOKIE_NAME = 'diskartech_admin_owner';

    public function claim(Admin $admin, string $token, ?string $previousToken = null): bool
    {
        // A conditional update lets only one login claim this account, including
        // simultaneous requests on different backend instances.
        return $admin->newQuery()->whereKey($admin->getKey())
            ->where(function ($query) use ($previousToken) {
                $query->whereNull('active_session_token')
                    ->orWhereNull('active_session_expires_at')
                    ->orWhere('active_session_expires_at', '<=', now()->getTimestamp());
                // Password-verified reauthentication may rotate this browser's
                // own token, including when its server-side session was lost.
                if ($previousToken !== null) {
                    $query->orWhere('active_session_token', hash('sha256', $previousToken));
                }
            })->update([
                'active_session_token' => hash('sha256', $token),
                'active_session_expires_at' => $this->expiresAt(),
            ]) === 1;
    }

    public function browserToken(Request $request): ?string
    {
        $token = $request->session()->get(self::SESSION_KEY);
        if ($token !== null) return is_string($token) ? $token : null;

        // Laravel's EncryptCookies middleware authenticates/decrypts this cookie.
        // A browser fingerprint or IP address is not proof of session ownership.
        $cookie = $request->cookie(self::COOKIE_NAME);
        return is_string($cookie) && preg_match('/^[A-Za-z0-9]{64}$/D', $cookie) ? $cookie : null;
    }

    public function rememberBrowser(Response $response, string $token): Response
    {
        $response->headers->setCookie(Cookie::make(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: max(1, (int) config('session.lifetime', 120)),
            path: config('session.path', '/'),
            domain: config('session.domain'),
            secure: config('session.secure') ?? request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));
        return $response;
    }

    public function forgetBrowser(Response $response): Response
    {
        $response->headers->setCookie(Cookie::forget(self::COOKIE_NAME, config('session.path', '/'), config('session.domain')));
        return $response;
    }

    public function refresh(Admin $admin, string $token): bool
    {
        $owner = $admin->newQuery()->whereKey($admin->getKey())
            ->where('active_session_token', hash('sha256', $token))
            ->where('active_session_expires_at', '>', now()->getTimestamp());

        $updated = (clone $owner)->update(['active_session_expires_at' => $this->expiresAt()]);

        // Some database drivers report zero when requests in the same second
        // leave the expiry unchanged. Verify ownership rather than logging out.
        return $updated === 1 || $owner->exists();
    }

    public function release(Admin $admin, string $token): void
    {
        // An old browser must never clear a newer browser's login.
        $admin->newQuery()->whereKey($admin->getKey())
            ->where('active_session_token', hash('sha256', $token))
            ->update(['active_session_token' => null, 'active_session_expires_at' => null]);
    }

    private function expiresAt(): int
    {
        return now()->addMinutes(max(1, (int) config('session.lifetime', 120)))->getTimestamp();
    }
}
