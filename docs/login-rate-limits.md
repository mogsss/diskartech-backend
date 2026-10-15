# Mobile API login rate limits

`POST /api/login` accepts at most 5 requests per minute for one email/IP pair,
with an additional 30 requests per minute per IP across all emails.
`POST /api/google-login` has a separate limit of 10 requests per minute per IP.
Successful logins, failed credentials, unknown emails, and invalid payloads all
count. Logging in successfully does not clear the counters.

The sixth password request for a pair, the 31st password request for an IP, or
the 11th Google request for an IP returns HTTP 429 before the controller issues
a token. Responses contain `code: login_rate_limited`, a numeric `retry_after`
in seconds, and the `Retry-After` / `X-RateLimit-*` headers. The mobile login
screen shows a waiting message for either sign-in method. There is no automatic
retry or permanent account lock. A pair block does not block that account from
another IP; people sharing one IP have separate email allowances until the
overall IP cap is reached. Google and password limits are independent.

Laravel named throttle middleware enforces the limits in `routes/api.php` and
`AppServiceProvider`. Email identifiers are trimmed and lowercased for the
limiter only; this does not change passwords or credential validation. Email
and IP identifiers are hashed before being used as cache keys.

Deploy the backend changes to Render. Use the existing persistent cache store
(`CACHE_STORE=database` in `.env.example`) and its existing `cache` table. An
array cache cannot enforce limits across production requests; a local file
cache does not share counters between instances. No new migration, secret,
package, or native build is required. The mobile change needs the updated JS
bundle. Admin web login has its own limiter documented in
[admin-login-rate-limits.md](admin-login-rate-limits.md). Registration and other
APIs are outside this change.

Verification: `php artisan test --compact --filter=LoginRateLimitTest` uses
isolated SQLite and an array cache, with no provider requests. It covers token
issuance prevention, expiry/recovery, case/whitespace variants, malformed
inputs, different emails/IPs, IP-wide limits and the Google endpoint.

Known separate authentication issue: the existing Google login controller
accepts an email without verifying a Google ID token on the backend. Throttling
does not establish that the caller owns that Google account. Server-side ID
token verification is still required; this change does not claim to fix it.
