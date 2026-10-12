# Mobile password recovery

Login → Forgot Password opens a single screen with three stages: account email, six-digit email code, then new password and confirmation. Only manually registered student, employer, and household accounts are eligible. Google registrations use Continue with Google. Historical records without a registration-method flag cannot be identified reliably; no email-domain inference is used.

The public endpoints are `POST /api/password/forgot`, `/api/password/verify-code`, and `/api/password/reset`. The existing Gmail OAuth sender sends recovery messages synchronously, so no additional mail credentials or queue configuration is needed. Request responses are generic for missing, admin, and Google accounts. Delivery failures return a service-unavailable response; provider bodies and credentials are not logged.

Recovery challenges live in `mobile_password_resets`, independently from email-verification OTP fields. Codes expire after 10 minutes and permit five incorrect attempts. Codes are stored as keyed SHA-256 hashes. Verification consumes the code and returns a random single-use reset token, stored as a hash, that expires after 10 minutes. The app holds this token only in memory. Resending invalidates prior codes and grants. Requests are limited per IP, per email (60-second cooldown and three per hour), and by the persistent challenge's send timestamp and attempt counter.

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
