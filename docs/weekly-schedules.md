# Weekly availability and job hours

Students set their free hours per day from Profile → Availability → Edit. Employers and households set required hours inside each job post's Work schedule section. Both editors show one weekly list: turn on a day, choose a preset or exact hours, then save. Day cards show saved hours at a glance and expand one at a time. Additional ranges and copying to other days are optional; up to three ranges per day remain supported. Custom end times earlier than start times automatically continue into the next day, with the ending weekday shown before adding. Copying can enable new days and replaces their hours only after selecting destinations and pressing Apply hours. Cancelling leaves saved values unchanged.

Times are recurring local wall times in **Asia/Manila**, formatted `HH:MM`. Endpoints are exclusive: a shift ending at 12:00 does not overlap a shift starting at 12:00. `ends_next_day: true` explicitly crosses midnight; Sunday ranges wrap into Monday. Whole day is 00:00 → next-day 00:00 (24 hours). A slot may not exceed 24 hours. Overlapping ranges, including across the week boundary, are rejected server-side. The editor merges overlapping or adjacent ranges within the same start day and keeps gaps between classes.

## Data storage

Migration: `2026_10_20_180000_create_weekly_schedule_slots.php`.

| Table | Parent | Fields |
| --- | --- | --- |
| `student_availability_slots` | `student_id` → `students.id` | `id`, `day_of_week`, `start_time`, `end_time`, `ends_next_day` |
| `job_schedule_slots` | `job_id` → `available_jobs.id` | `id`, `day_of_week`, `start_time`, `end_time`, `ends_next_day` |

`job_applications.work_schedule_slots` is a nullable JSON snapshot of agreed hours at hiring. Job edits do not change an already accepted contract's snapshot. Editing an older listing freezes parseable active legacy contracts before changing the listing.

No destructive backfill runs. Existing `available_days` / `time_slot` values remain readable and known presets are interpreted as daily ranges. Unknown free text is not guessed. New writes persist normalized exact slots and refresh the old columns with readable summaries. Old app versions cannot overwrite a saved per-day student schedule; job metadata edits are allowed if the old schedule fields are unchanged. Exact summaries may exceed the old field's limit, so the legacy summary is capped at 255 characters; updated screens use the full slot records.

## API

Authenticated `GET /api/schedule-capabilities` returns `weekly_schedule_version: 1` only after all tables/columns exist. New clients check this before writing so an older server cannot silently drop exact hours.

`POST /api/student/update-availability`, `POST /api/jobs`, and `PUT /api/jobs/{id}` accept:

```json
{
  "schedule_slots": [
    { "day_of_week": "Mon", "start_time": "09:30", "end_time": "12:00", "ends_next_day": false },
    { "day_of_week": "Mon", "start_time": "13:00", "end_time": "15:00", "ends_next_day": false },
    { "day_of_week": "Wed", "start_time": "22:00", "end_time": "06:00", "ends_next_day": true }
  ]
}
```

Job create/update still requires its normal title, description, salary, category, etc. The server derives legacy day and time fields from the validated slots. Responses expose `schedule_slots` on student and job models, including when nested inside application details. Saves replace the owning account's slots atomically. Foreign keys cascade when a parent row is physically deleted.

## Matching and hiring

Deterministic interval checks produce `schedule_match` with `status` (`full`, `partial`, `conflict`, `unknown`), `percentage`, `required_minutes`, and `covered_minutes`. These are separate from AI skills/ranking scores. The AI cannot mark a conflicting schedule as a perfect overall match. The fallback uses actual covered minutes rather than shared day names. Cache keys include current exact availability, job hours, skills, and active commitments. Schedule coverage remains separate from ranking and placement.

Active accepted/hired contracts reduce available hours. Hiring checks availability again inside a transaction, locks the student to serialize competing hires, and locks the job and application before freezing agreed hours. A partial/conflicting schedule is rejected with a validation error; typed free text cannot override posted hours. Ending/rejecting/cancelling/completing a contract releases its occupied hours.

Conservative cases: recommendation feeds have no proposed contract dates, so current and future active commitments reserve recurring hours. At hiring, contracts with explicitly disjoint date ranges do not conflict. Within overlapping dates, comparison is weekly, so very short date-bound contracts may need review. Unparseable active legacy contract hours reserve the week until clarified instead of being treated as free. Unparseable legacy job hours retain the old manual hiring path and are not called a full schedule match. No calendar integration, automatic hiring, or travel-time buffer is added.

## Deploy and verify

Deploy the backend first before publishing the frontend. `docker-entrypoint.sh` explicitly runs the weekly-schedule migration with `--force --no-interaction` and stops startup on failure. The migration can resume after an interrupted MySQL deployment without erasing already-created slot records. For a non-Docker environment, run `php artisan migrate --path=database/migrations/2026_10_20_180000_create_weekly_schedule_slots.php` against the intended database (add `--force` for production). This migration adds two tables and one nullable column. It has been exercised on isolated SQLite test fixtures; no shared or production database was migrated by this implementation task.

Development UI changes load with the existing dev server. No native dependency was added; the installed date/time-picker dependency is reused.

Verification commands:

```text
php -d extension=zip vendor/phpunit/phpunit/phpunit --filter "WeeklyScheduleTest|StudentAvailabilityTest|HiringInputValidationTest|JobMatchingTest|HirerLocationSyncTest|JobSalaryTypeTest|EmployerApplicantAuthorizationTest" --no-progress
```

From the frontend:

```text
node scripts/test-weekly-schedule.cjs
node scripts/test-job-posting.cjs
node node_modules/typescript/bin/tsc --noEmit
```

On a device, save split Monday hours, restart and verify they reload; add a Wednesday night range; create/edit a household and an employer job; review the student's full/partial label; confirm a conflicting hire is rejected and adjacent non-overlapping work can be accepted. Browser layout checks exercise the shared components in light/dark modes at 320, 390 and 768 pixels; native device checks remain a separate manual step.
