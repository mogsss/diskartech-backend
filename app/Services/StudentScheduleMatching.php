<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\Jobs;
use App\Models\Student;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StudentScheduleMatching
{
    public function busy(Student $student, ?int $except = null, ?string $startDate = null, ?string $endDate = null): array
    {
        if (!Schema::hasTable('job_applications')) return [];
        $slots = [];
        $contracts = JobApplication::with('job')->where('student_id', $student->id)
            ->whereIn('status', ['accepted', 'hired'])->when($except, fn ($q) => $q->where('id', '!=', $except))->get();
        foreach ($contracts as $contract) {
            $end = $this->date($contract->end_date);
            if ($end && $end < new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'))) continue;
            $start = $this->date($contract->start_date);
            $candidateStart = $this->date($startDate);
            $candidateEnd = $this->date($endDate);
            if (($end && $candidateStart && $end < $candidateStart) || ($candidateEnd && $start && $candidateEnd < $start)) continue;
            $ranges = $this->contractSlots($contract);
            // Unknown legacy free text requires manual review; it cannot imply a free week.
            $slots = array_merge($slots, $ranges ?: WeeklySchedule::legacy(WeeklySchedule::DAYS, 'Whole Day'));
        }
        return $slots;
    }

    public function contractSlots(JobApplication $contract): array
    {
        if ($contract->work_schedule_slots !== null) return $contract->work_schedule_slots;
        $text = $contract->work_schedule ?? '';
        if (preg_match('/^([A-Za-z, ]+)\s*[|•]\s*(.+)$/u', $text, $m)) return WeeklySchedule::legacy(explode(',', trim($m[1])), trim($m[2]));
        return !$text ? ($contract->job?->schedule_slots ?? []) : [];
    }

    public function freezeContracts(Jobs $job): void
    {
        if (!Schema::hasColumn('job_applications', 'work_schedule_slots')) return;
        foreach ($job->applications()->whereIn('status', ['accepted', 'hired'])->whereNull('work_schedule_slots')->get() as $contract) {
            $contract->setRelation('job', $job);
            $contract->update(['work_schedule_slots' => $this->contractSlots($contract)]);
        }
    }

    public function compare(Student $student, Jobs $job): array
    {
        return WeeklySchedule::compare($job->schedule_slots, $student->schedule_slots, $this->busy($student));
    }

    public function applicationWarning(Student $student, Jobs $job): ?string
    {
        $slots = $job->schedule_slots;
        if (!$slots) return null;
        if (!$student->schedule_slots) {
            return 'You have no saved availability to compare with this job. Review the work hours before continuing, and discuss your schedule with the hirer.';
        }
        if (WeeklySchedule::compare($slots, $student->schedule_slots)['status'] !== 'full') {
            return 'Your saved availability does not cover all the work hours for this job. You can still apply, but discuss the schedule with the hirer before accepting any work.';
        }
        if ($this->compare($student, $job)['status'] !== 'full') {
            return 'These work hours overlap an active contract, or its hours are not confirmed. You can still apply, but the contract conflict must be resolved before you can be hired.';
        }
        return null;
    }

    public function assertCanHire(JobApplication $application): void
    {
        $student = Student::whereKey($application->student_id)->lockForUpdate()->firstOrFail();
        $job = Jobs::whereKey($application->job_id)->lockForUpdate()->firstOrFail();
        $persisted = JobApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
        $slots = $persisted->work_schedule_slots ?? $job->schedule_slots;
        if (!$slots) return; // Unparseable legacy jobs retain their existing manual hiring flow.
        // Availability is advisory when applying. Only another active contract blocks hiring.
        $result = WeeklySchedule::compare($slots, $slots, $this->busy($student, $application->id, $application->start_date, $application->end_date));
        if ($result['status'] !== 'full') {
            throw ValidationException::withMessages(['work_schedule' => 'An active contract blocks these hours. Review its work schedule before hiring.']);
        }
        $application->work_schedule_slots = $slots;
        $application->work_schedule = WeeklySchedule::summary($slots);
    }

    private function date(?string $value): ?\DateTimeImmutable
    {
        foreach (['Y-m-d', 'F j, Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value ?? '', new \DateTimeZone('Asia/Manila'));
            if ($date && $date->format($format) === $value) return $date;
        }
        return null;
    }
}
