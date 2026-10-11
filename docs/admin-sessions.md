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

On Render, configure persistent sessions and keep the existing `APP_KEY` unchanged:

```dotenv
SESSION_DRIVER=database
SESSION_EXPIRE_ON_CLOSE=false
SESSION_SECURE_COOKIE=true
```

The included sessions-table migration creates persistent storage if needed and
preserves an existing table; rollback intentionally retains shared session data.
The Docker entrypoint requires this migration to succeed before startup.
File sessions on Render can disappear during a
redeploy even while the admin ownership lock remains in the database. Database
sessions avoid that mismatch. Do not regenerate the application key during deploys;
doing so invalidates encrypted session and ownership cookies.

## Returning to the same browser

An authenticated browser visiting either login URL goes directly to the admin
dashboard. Login also sets an encrypted, HttpOnly, SameSite=Lax ownership cookie
whose expiry is refreshed with protected admin activity. If the server session
disappears, correct credentials and that matching cookie allow the same browser to
reclaim its own lock. Reauthentication rotates the token atomically. A different
browser, altered cookie, or old token cannot replace an active owner.

The cookie is a recovery proof, not an automatic login: a lost authentication
session still requires the admin password. Login locks created before this update
cannot be recovered if both the original session and recovery cookie are missing;
they release after the existing idle expiry. Clearing cookies or opening an
incognito window also removes browser ownership proof.

## Expiration and logout

Signing out releases ownership immediately. Protected admin requests extend the
expiry by `SESSION_LIFETIME` minutes (default: 120). This includes the admin
notification polling requests while the dashboard is open. Closing the browser
without signing out does not immediately release ownership. The same browser can
return while its cookies remain valid; another browser must wait for logout or
expiry. Logout clears the recovery cookie as well as the lock. An expired browser
cannot revive its authentication or clear a newer browser's ownership.

The database stores only a SHA-256 hash of a server-generated login token. A
conditional update makes competing login claims atomic across backend instances.
The token remains valid when the current browser rotates its session ID, including
after a password change.

Validation: `php artisan test --filter=AdminSessionTest` covers independent browser
sessions, encrypted-cookie recovery after file/database session loss, password
verification, logout, expiry, token rotation and migration compatibility.
