# Admin login rate limits

Both `POST /admin/login` and the `POST /login` alias use the same named
`admin-login` limiter. They accept up to 5 requests per minute per normalized
email/IP pair, with an additional cap of 30 requests per minute per IP across
emails. Changing the URL does not reset either allowance.

The limiter runs before credential validation and session ownership claims.
Successful logins, invalid credentials, unknown accounts, invalid inputs, and
session ownership rejections count. Successful sign-in and logout do not clear
the counters. The IP counter also counts requests blocked by the pair limit.
The restriction expires automatically and does not permanently lock the account;
other IPs have independent allowances.

Blocked browser requests render the existing login page with HTTP 429, an English
waiting message, the submitted email (HTML escaped), and an empty password field.
`Retry-After` and rate-limit headers give the remaining wait in seconds, and the
response is marked `Cache-Control: no-store, private`. JSON requests receive
`code: login_rate_limited`, `message`, and numeric `retry_after` with HTTP 429.
GET login pages remain accessible.

The limiter lowercases and normalizes the email using the same whitespace/control
character normalization as admin credential validation. It never modifies the
password. Email/IP identifiers are hashed in cache keys. Mobile API login uses
separate counters and its existing limits.

Deploy these changes to the Render backend's `main` branch. Use the existing
persistent cache store (`CACHE_STORE=database`) and its `cache` table. `array`
cannot preserve counters between production requests; instance-local file caches
do not share limits between multiple backend instances. No new migration or
package is required. The Docker entrypoint rebuilds the route cache on deployment.

Run `php artisan test --filter="AdminLoginRateLimitTest|AdminSessionTest|AdminDomainTest|LoginRateLimitTest"`
to check the request limits, browser/JSON responses, alias sharing, cooldown expiry,
session protection, domain routing, and existing mobile login limits with isolated
SQLite fixtures and no real login attempts against production.
