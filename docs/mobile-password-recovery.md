# Mobile password recovery

Login → Forgot Password opens a single screen with three stages: account email, six-digit email code, then new password and confirmation. Only manually registered student, employer, and household accounts are eligible. Google registrations use Continue with Google. Historical records without a registration-method flag cannot be identified reliably; no email-domain inference is used.

The public endpoints are `POST /api/password/forgot`, `/api/password/verify-code`, and `/api/password/reset`. The existing Gmail OAuth sender sends recovery messages synchronously, so no additional mail credentials or queue configuration is needed. Request responses are generic for missing, admin, and Google accounts. Delivery failures return a service-unavailable response; provider bodies and credentials are not logged.

Recovery challenges live in `mobile_password_resets`, independently from email-verification OTP fields. Codes expire after 10 minutes and permit five incorrect attempts. Codes are stored as keyed SHA-256 hashes. Verification consumes the code and returns a random single-use reset token, stored as a hash, that expires after 10 minutes. The app holds this token only in memory. Resending invalidates prior codes and grants. Requests are limited per IP, per email (one send attempt every 90 seconds by default), and by the persistent challenge's send timestamp and attempt counter. The previous three-per-hour email cap is no longer used; stale hourly counters cannot extend the new cooldown.

The resend delay is configured by `services.otp.password_reset_resend_cooldown` in `config/services.php`, optionally overridden with `PASSWORD_RESET_RESEND_COOLDOWN=90` in the backend environment. This setting is separate from registration verification and account-deletion OTP limits. The mobile screen follows the backend's `retry_after` value and displays minutes/seconds, with a 90-second fallback. Deploy backend changes (and reload/reopen the mobile recovery screen to discard an already-running old timer); configuration changes require a configuration-cache refresh or Render redeploy.

Password confirmation, uppercase, digit, special character, minimum eight characters and bcrypt's maximum 72 UTF-8 bytes are enforced server-side and in the app. Credentials are not trimmed or rewritten. Reusing the old password is rejected. Reset updates the password, revokes all Sanctum sessions, and consumes the grant in a database transaction. It does not approve the account or mark its email verified. The user returns to Login afterwards.

Deploy the backend before testing on the app. The Docker entrypoint runs the required migration; for deployments outside Docker, run:

```sh
php artisan migrate --path=database/migrations/2026_10_12_140000_create_mobile_password_resets_table.php --force
```

Validation:

```sh
php artisan test --compact --filter='PasswordResetTest|EmailOtpTest|ChangePasswordTest'
```

Frontend checks:

```sh
node --test scripts/test-password-recovery.cjs scripts/test-password-change.cjs scripts/test-session-expiry.cjs
npx tsc --noEmit
```
