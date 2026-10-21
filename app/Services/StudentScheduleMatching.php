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

    public function assertCanHire(JobApplication $application): void
    {
        $student = Student::whereKey($application->student_id)->lockForUpdate()->firstOrFail();
        $job = Jobs::whereKey($application->job_id)->lockForUpdate()->firstOrFail();
        $persisted = JobApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
        $slots = $persisted->work_schedule_slots ?? $job->schedule_slots;
        if (!$slots) return; // Unparseable legacy jobs retain their existing manual hiring flow.
        $result = WeeklySchedule::compare($slots, $student->schedule_slots, $this->busy($student, $application->id, $application->start_date, $application->end_date));
        if ($result['status'] !== 'full') {
            throw ValidationException::withMessages(['work_schedule' => 'The student cannot cover all required hours, or has another active contract at this time. Review their availability before hiring.']);
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
