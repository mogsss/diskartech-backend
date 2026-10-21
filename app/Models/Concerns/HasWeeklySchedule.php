<?php

namespace App\Models\Concerns;

use App\Models\Student;
use App\Models\WeeklyScheduleSlot;
use App\Services\WeeklySchedule;
use Illuminate\Support\Facades\Schema;

trait HasWeeklySchedule
{
    public function weeklySlotTable(): string
    {
        return $this instanceof Student ? 'student_availability_slots' : 'job_schedule_slots';
    }

    public function weeklyScheduleSlots()
    {
        $related = (new WeeklyScheduleSlot)->setTable($this->weeklySlotTable());
        return new \Illuminate\Database\Eloquent\Relations\HasMany($related->newQuery(), $this,
            $related->qualifyColumn($this instanceof Student ? 'student_id' : 'job_id'), $this->getKeyName());
    }

    public static function bootHasWeeklySchedule(): void
    {
        static::addGlobalScope('weekly_schedule', function ($query) {
            // Reads still work during deployment before the additive migration runs.
            if (Schema::hasTable((new static)->weeklySlotTable())) $query->with('weeklyScheduleSlots');
        });
    }

    public function initializeHasWeeklySchedule(): void
    {
        $this->append('schedule_slots');
        $this->makeHidden('weeklyScheduleSlots');
    }

    public function getScheduleSlotsAttribute(): array
    {
        $rows = $this->relationLoaded('weeklyScheduleSlots') ? $this->getRelation('weeklyScheduleSlots') :
            (Schema::hasTable($this->weeklySlotTable()) && $this->exists ? $this->weeklyScheduleSlots()->get() : collect());
        if ($rows->isNotEmpty()) return WeeklySchedule::ordered($rows->map(fn ($s) => $s->only(['day_of_week', 'start_time', 'end_time', 'ends_next_day']))->values()->all());
        return WeeklySchedule::legacy($this->available_days, $this->time_slot);
    }

    public function replaceWeeklySlots(array $slots): void
    {
        abort_unless(Schema::hasTable($this->weeklySlotTable()), 503, 'Weekly schedules are not ready. Run the backend migrations first.');
        $this->weeklyScheduleSlots()->delete();
        $this->weeklyScheduleSlots()->createMany($slots);
        $this->unsetRelation('weeklyScheduleSlots');
    }
}
