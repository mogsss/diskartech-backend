<?php

namespace App\Support;

use App\Models\Admin;

final class AdminSession
{
    public const SESSION_KEY = 'admin_login_token';

    public function claim(Admin $admin, string $token): bool
    {
        // A conditional update lets only one login claim this account, including
        // simultaneous requests on different backend instances.
        return $admin->newQuery()->whereKey($admin->getKey())
            ->where(function ($query) {
                $query->whereNull('active_session_token')
                    ->orWhereNull('active_session_expires_at')
                    ->orWhere('active_session_expires_at', '<=', now()->getTimestamp());
            })->update([
                'active_session_token' => hash('sha256', $token),
                'active_session_expires_at' => $this->expiresAt(),
            ]) === 1;
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
