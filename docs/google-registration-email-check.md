# Existing email check during Google registration

After selecting a native Google account, all three registration screens call the public `POST /api/register/check-email` endpoint with its email. It returns only `{ "status": "success", "exists": true|false }` and never issues an authentication token, changes an account, or returns profile data. Email format, length, and control characters are validated, and lookup trims surrounding whitespace and ignores letter case. Requests are limited to 15 per minute per IP and responses use `Cache-Control: no-store`.

If the email already belongs to an account of any role, registration shows Account already exists with Go to Login and Cancel actions. The existing registration selection stays intact. Login receives the selected email and starts the existing Google sign-in flow. After canceling the notice, the next selection opens the native account chooser. New emails populate the registration form only after the check succeeds; network errors and malformed responses offer a retry instead of proceeding. The registration endpoints still enforce uniqueness when the account is finally submitted.

Deploy the backend endpoint before testing the mobile changes. No migration or new environment variable is needed. Docker deployment rebuilds the Laravel route cache automatically.

Validation:

```sh
php artisan test --compact --filter='RegistrationEmailCheckTest|ChangePasswordTest'
```

```sh
node --test scripts/test-google-registration.cjs scripts/test-google-sign-in.cjs scripts/test-session-expiry.cjs
```
