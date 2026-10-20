# Hirer profile location and job pins

Saving an employer or household profile through `POST /api/user/update-profile` with a valid latitude/longitude pair also updates the coordinates of that user's existing `available_jobs`. Address labels continue to come from the current profile. Map viewers and Nearby distance calculations use the synchronized job coordinates after fetching fresh data.

Profile and job writes share one transaction. Invalid coordinates save neither. Jobs belonging to other accounts are excluded; titles, posting dates, statuses, applications, and contracts are not changed. Closed and hidden job posts receive the current coordinates too, so reopening a post does not restore its old pin. Text-only API edits without coordinates leave job pins unchanged.

Re-saving an unchanged profile pin also repairs older posts whose coordinates were stale or missing. Posts that already match are not rewritten. New job creation reads current profile coordinates under its posting transaction's lock, so a request prepared before a profile edit cannot reintroduce the old pin afterward.

No migration, environment variable, or mobile rebuild is required. After deploying the backend, an account edited before this fix can open Edit profile and press Save changes once to synchronize existing posts. Student screens need a refresh to display the new data; this does not add live push updates to an already-open screen.

Verification: `php artisan test --filter='HirerLocationSyncTest|PinamalayanLocationTest|HirerNameProfileUpdateTest|EmployerJobQuotaTest|JobSalaryTypeTest'`.
