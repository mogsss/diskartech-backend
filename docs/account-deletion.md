# Mobile account deletion

Settings > Account > Delete account and the Privacy Policy's related-information link open the mobile confirmation screen. Students, employers and household accounts may delete, including Google accounts and accounts with ongoing work or a Pro plan.

## Confirmation

- `POST /api/user/deletion-code` sends a purpose-specific email to the authenticated account's stored email. The request cannot choose a recipient. Existing Gmail OTP configuration is reused.
- `DELETE /api/user/account` requires the six-digit `code`, `confirmation: "DELETE"`, and `acknowledged: true`.
- Both endpoints require a real Sanctum personal access token and a mobile role. The code is bound to that account and token, stored as an HMAC, expires after ten minutes, and is locked after five failed attempts. There is one active code per account. Resending replaces the old challenge and requires at least sixty seconds; at most three sends are allowed per hour per account, across devices.
- Codes and provider error payloads are never returned or logged. Failed delivery leaves the previous challenge unchanged. Invalid confirmation cannot modify account records. Deletion, anonymization, token revocation, and challenge consumption happen in one database transaction.

## Result and retained records

- All Sanctum sessions are revoked. The login email is replaced with a random, non-deliverable address, the password is randomized, and the account role becomes `deleted`. The User model excludes these anonymous placeholders from authentication, public profiles, and ordinary account queries. Re-registering the original email creates a new account with a different ID and does not restore old records.
- Profile fields are cleared, names become `Deleted account`, verification and Pro access are disabled, saved jobs and password reset challenges are removed. Original IDs remain to preserve foreign keys.
- Job posts close. Pending/viewed/interview applications become cancelled. Accepted/hired engagements become terminated with a reason explaining account deletion; interview access ends. Completed/previously terminated/rejected records keep their statuses.
- Shared contracts, certificate/review history, payment records and safety reports remain. Job descriptions/locations and other shared historical content may still contain previously shared information. Firestore conversations, names/avatars already copied into messages, attachments and copies held by others are not purged by this endpoint. The screen and Privacy Policy disclose retained shared records and refer further requests to support.
- Deletion does not settle outstanding obligations, automatically refund payments, or cancel an external checkout. A payment arriving after deletion is recorded without reactivating Pro or setting a new `granted_until`.
- After backend success the app attempts to unregister the current account's push token and clear its cached account/session data. Preferences remain. This mobile cleanup is best effort; remote tokens are revoked regardless.

## Deployment and storage cleanup

The new migration is `2026_10_16_120000_create_account_deletion_tables.php`. Render's entrypoint explicitly runs it before serving traffic. No new environment variables or npm/composer packages are required. For local development, run:

```powershell
php artisan migrate --path=database/migrations/2026_10_16_120000_create_account_deletion_tables.php
```

Profile file references are saved in `account_deletion_files` within the deletion transaction. A background loop in the Render entrypoint retries batches every five minutes using:

```powershell
php artisan accounts:cleanup-files --limit=25
```

The command removes local public uploads and Cloudinary authenticated raw documents/public image uploads. It never fetches arbitrary external avatars, preserves references used by another profile, and retains failed tasks for retry. Provider outages may delay physical file removal. The loop stops while a free Render instance is asleep and resumes when the instance starts. Monitor cleanup backlog and `Deleted account file cleanup will be retried` logs; paths, emails and provider exception bodies are omitted from those logs. On other hosts, schedule this command periodically.

Do not physically remove anonymous account/profile placeholders without reviewing foreign-key cascades: doing so could erase the other participant's work and safety history.

## Verification

Run `php artisan test --filter=AccountDeletionTest` for role/authentication boundaries, code/session binding, expiry, attempt/send limits, mail failures, transaction rollback, active work/Pro handling, anonymization, session revocation, shared-history preservation, and storage cleanup/retries. Existing password, OTP, Cloudinary, subscription, and admin-user tests cover the shared components affected by this change.
