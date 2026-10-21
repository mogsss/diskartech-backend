<?php

namespace App\Services;

use App\Models\JobApplication;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class InterviewSchedule
{
    public static function startsAt(JobApplication $application): ?CarbonImmutable
    {
        try {
            $parts = $application->interview_date ? date_parse($application->interview_date) : null;
            if ($parts && !$parts['error_count'] && !$parts['warning_count']
                && $parts['year'] && $parts['month'] && $parts['day']
                && preg_match('/^(0?[1-9]|1[0-2]):[0-5]\d [AP]M$/', (string) $application->interview_time)) {
                $time = CarbonImmutable::createFromFormat('!h:i A', $application->interview_time, 'Asia/Manila');
                return CarbonImmutable::parse($application->interview_date, 'Asia/Manila')
                    ->startOfDay()->setTime($time->hour, $time->minute);
            }
        } catch (\Throwable) {
            // An invalid legacy schedule must be corrected before proceeding.
        }
        return null;
    }

    public static function assertCanHire(JobApplication $application): void
    {
        // Jobs without an interview retain their existing hiring flow.
        if ($application->status !== 'interview' && !$application->interview_date && !$application->interview_time) return;
        if (in_array($application->status, ['accepted', 'hired'], true)) return;
        $start = self::startsAt($application);
        if (!$start) {
            throw ValidationException::withMessages(['interview_time' => 'Set a valid interview date and time before hiring this applicant.']);
        }
        if (now()->lessThan($start)) {
            throw ValidationException::withMessages(['interview_time' => 'Hiring becomes available at the scheduled interview date and time (Philippine time).']);
        }
    }
}
