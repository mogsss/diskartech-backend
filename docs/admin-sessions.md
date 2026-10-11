# Admin login sessions

Each admin account can have one active browser session. Correct credentials on a
second browser show an "already signed in" error; the original browser stays signed
in. Different admin accounts can still sign in at the same time. Tabs sharing a
browser session use the same login.

## Deployment

Deploy the backend changes. The Docker entrypoint applies the required migration
automatically and stops startup if it fails. For local or other deployments, run:

```sh
php artisan migrate --force
```

The migration adds nullable ownership and expiry columns to `admins`. Existing
authenticated sessions are adopted on their first protected request after deployment;
if several browsers were already signed in, the first one to request a protected
page keeps access.

## Expiration and logout

Signing out releases ownership immediately. Protected admin requests extend the
expiry by `SESSION_LIFETIME` minutes (default: 120). This includes the admin
notification polling requests while the dashboard is open. Closing the browser
without signing out does not immediately release ownership; login is available
again after expiry. An expired browser cannot revive its login or clear a newer
browser's ownership.

The database stores only a SHA-256 hash of a server-generated login token. A
conditional update makes competing login claims atomic across backend instances.
The token remains valid when the current browser rotates its session ID, including
after a password change.
