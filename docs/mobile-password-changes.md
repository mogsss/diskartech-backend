# Mobile password changes

Settings → Account → Change password is available to manually registered students, employers, and households using manual login. It is hidden for Google registration or a current Google sign-in session, and the API also rejects password changes in those cases.

Deploy the backend route/controllers and run `php artisan migrate --force` before testing the updated mobile screen. The migration adds `users.registered_with_google` with a default of false and leaves existing passwords intact. Render's startup script also runs this required migration. No new dependency is required.

`GET /api/user/account` returns the authenticated account's `registered_with_google`, `authentication_method`, and `can_change_password` metadata. Google-issued Sanctum tokens have the literal `auth:google` marker; wildcard token abilities alone do not identify Google sessions. Registration records the boolean flag sent by the app; it is account metadata, not proof of Google identity.

`PUT /api/user/password` requires the current Sanctum session, is limited to five requests per minute, and accepts:

- `current_password`: the current **DiskarTech** password, not the Google password.
- `password`: at least eight characters, including an uppercase ASCII letter, a digit, and one of `!@#$%^&*(),.?":{}|<>`.
- `password_confirmation`: an exact match for the new password.

Passwords retain their original spaces and punctuation. Control characters and new passwords exceeding bcrypt's 72-byte limit are rejected. Reusing the current password is rejected. The request only updates the authenticated account; client-supplied account IDs are ignored.

The password update and revocation of other Sanctum tokens happen in one transaction. The requesting device keeps its token. No plaintext passwords or hashes are returned.

Existing Google-registered accounts have an automatically generated DiskarTech password. Old records/tokens do not identify their registration/login method reliably, so no historical registration source is guessed from email domains or password hashes. Existing Google users should sign in with Google again after deployment to obtain a tagged session and hide the option. New Google registrations persist the flag across devices and future logins. Google sign-in does not permanently relabel manually registered accounts; manual login can show their option again.

Checks:

```sh
php artisan test --compact --filter=ChangePasswordTest
```

Frontend checks (in `DISKARTECH`):

```sh
node scripts/test-password-change.cjs
npx tsc --noEmit
```
