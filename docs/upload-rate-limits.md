# Profile and verification upload limits

Each authenticated account has one shared allowance of **5 upload requests per
minute** across these endpoints:

- `POST /api/student/upload-doc` (avatar, resume, school ID and COR/COE).
- `POST /api/employer/add-profile` (employer and household avatars).
- `POST /api/upload-verification-doc` (employer/household ID and employer permit).

The limiter keys the authenticated user ID, not the submitted user ID, document
type, IP address or access token. Switching files, routes, devices or tokens
does not reset the same account's allowance. Different accounts have independent
allowances even on one network. Failed validation and provider failures count
as attempts; choosing/cancelling a file locally does not count. A request with
two verification files counts as one request.

The sixth request returns HTTP 429 **before** any controller upload, profile
update or analysis dispatch. Existing files and analysis results are preserved
for that blocked request. The response includes `code: upload_rate_limited`,
numeric `retry_after` seconds and `Retry-After` / `X-RateLimit-*` headers. The
profile and verification screens show a waiting popup for students, employers
and households. Waiting does not permanently lock the account, and the app does
not automatically retry uploads.

These are authenticated profile uploads. Registration uploads do not yet use
this per-account limiter; chat attachments keep their separate existing limit.
Reading documents/profile data and editing ordinary profile text do not spend
the upload allowance.

Deploy the backend change to Render and deliver the updated frontend JS bundle
for the popup wording. No new migration, credentials, package or native rebuild
is needed. Use the existing persistent limiter cache (`CACHE_STORE=database`
and its existing `cache` table); array caches cannot enforce production limits
across requests. The port configuration is unchanged.

Verification:

```
php artisan test --compact --filter='ProfileUploadRateLimitTest|CloudinaryUploadsTest|LoginRateLimitTest'
```

Tests use isolated SQLite, fake queues and mocked Cloudinary, never real uploads.
