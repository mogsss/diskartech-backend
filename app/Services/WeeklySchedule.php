<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Recurring weekly ranges in Asia/Manila; end points are exclusive. */
class WeeklySchedule
{
    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    public const PRESETS = [
        'Morning (6AM - 12PM)' => ['06:00', '12:00', false],
        'Afternoon (1PM - 5PM)' => ['13:00', '17:00', false],
        'Evening (6PM - 10PM)' => ['18:00', '22:00', false],
        'Night (10PM - 6AM)' => ['22:00', '06:00', true],
        'Whole Day' => ['00:00', '00:00', true],
    ];

    public static function minute(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    public static function validate(mixed $input): array
    {
        $data = Validator::make(['schedule_slots' => $input], [
            'schedule_slots' => ['required', 'array', 'min:1', 'max:21'],
            'schedule_slots.*' => ['required', 'array:day_of_week,start_time,end_time,ends_next_day'],
            'schedule_slots.*.day_of_week' => ['required', 'in:'.implode(',', self::DAYS)],
            'schedule_slots.*.start_time' => ['required', 'date_format:H:i'],
            'schedule_slots.*.end_time' => ['required', 'date_format:H:i'],
            'schedule_slots.*.ends_next_day' => ['required', 'boolean'],
        ])->validate()['schedule_slots'];
        $counts = [];
        foreach ($data as $i => &$slot) {
            $slot['ends_next_day'] = (bool) $slot['ends_next_day'];
            $duration = self::minute($slot['end_time']) + ($slot['ends_next_day'] ? 1440 : 0) - self::minute($slot['start_time']);
            $counts[$slot['day_of_week']] = ($counts[$slot['day_of_week']] ?? 0) + 1;
            if ($duration <= 0 || $duration > 1440 || $counts[$slot['day_of_week']] > 3) {
                throw ValidationException::withMessages(["schedule_slots.$i.end_time" => 'Use a positive range of up to 24 hours, with at most 3 ranges per day.']);
            }
        }
        unset($slot);
        // Includes ranges which overlap the following day or wrap Sunday to Monday.
        $ranges = self::intervals($data);
        for ($i = 1; $i < count($ranges); $i++) {
            if ($ranges[$i][0] < $ranges[$i - 1][1]) {
                throw ValidationException::withMessages(['schedule_slots' => 'Hours overlap. Adjust or remove the overlapping range.']);
            }
        }
        return self::ordered($data);
    }

    public static function ordered(array $slots): array
    {
        usort($slots, fn ($a, $b) => (array_search($a['day_of_week'], self::DAYS) <=> array_search($b['day_of_week'], self::DAYS)) ?: strcmp($a['start_time'], $b['start_time']));
        return $slots;
    }

    public static function legacy(mixed $days, ?string $time): array
    {
        if (is_string($days)) $days = json_decode($days, true);
        if (!is_array($days)) return [];
        $ranges = [];
        foreach (array_unique(array_map(fn ($d) => ucfirst(strtolower(substr(trim((string) $d), 0, 3))), $days)) as $day) {
            if (!in_array($day, self::DAYS, true)) continue;
            foreach (explode(' & ', $time ?? '') as $preset) {
                $values = self::PRESETS[trim($preset)] ?? match (strtolower(trim($preset))) {
                    'morning' => self::PRESETS['Morning (6AM - 12PM)'],
                    'afternoon' => self::PRESETS['Afternoon (1PM - 5PM)'],
                    'evening' => self::PRESETS['Evening (6PM - 10PM)'],
                    'night' => self::PRESETS['Night (10PM - 6AM)'],
                    'whole day' => self::PRESETS['Whole Day'],
                    default => null,
                };
                if (!$values) continue;
                $ranges[] = ['day_of_week' => $day, 'start_time' => $values[0], 'end_time' => $values[1], 'ends_next_day' => $values[2]];
            }
        }
        return self::ordered($ranges);
    }

    public static function days(array $slots): array
    {
        return array_values(array_filter(self::DAYS, fn ($day) => in_array($day, array_column($slots, 'day_of_week'), true)));
    }

    public static function summary(array $slots): string
    {
        $parts = [];
        foreach (self::days($slots) as $day) {
            $parts[] = $day.' '.implode(', ', array_map(fn ($s) => $s['start_time'].'-'.$s['end_time'].($s['ends_next_day'] ? ' (+1d)' : ''), array_values(array_filter($slots, fn ($s) => $s['day_of_week'] === $day))));
        }
        $text = implode('; ', $parts);
        return strlen($text) > 255 ? substr($text, 0, 227).'... (see weekly schedule)' : $text;
    }

    public static function intervals(array $slots): array
    {
        $ranges = [];
        foreach ($slots as $s) {
            $offset = array_search($s['day_of_week'], self::DAYS, true) * 1440;
            $start = $offset + self::minute($s['start_time']);
            $end = $offset + self::minute($s['end_time']) + ($s['ends_next_day'] ? 1440 : 0);
            if ($end > 10080) {
                $ranges[] = [$start, 10080];
                $ranges[] = [0, $end - 10080];
            } else $ranges[] = [$start, $end];
        }
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        return $ranges;
    }

    private static function union(array $ranges): array
    {
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as $r) {
            $last = count($merged) - 1;
            if ($last >= 0 && $r[0] <= $merged[$last][1]) $merged[$last][1] = max($merged[$last][1], $r[1]);
            else $merged[] = $r;
        }
        return $merged;
    }

    public static function compare(array $required, array $available, array $busy = []): array
    {
        $work = self::union(self::intervals($required));
        $free = self::union(self::intervals($available));
        $blocked = self::union(self::intervals($busy));
        foreach ($blocked as [$bs, $be]) {
            $next = [];
            foreach ($free as [$s, $e]) {
                if ($be <= $s || $bs >= $e) $next[] = [$s, $e];
                else {
                    if ($bs > $s) $next[] = [$s, $bs];
                    if ($be < $e) $next[] = [$be, $e];
                }
            }
            $free = $next;
        }
        $total = $covered = 0;
        foreach ($work as [$s, $e]) {
            $total += $e - $s;
            foreach ($free as [$fs, $fe]) $covered += max(0, min($e, $fe) - max($s, $fs));
        }
        $percentage = $total ? (int) floor($covered / $total * 100) : null;
        return ['status' => !$total || !$available ? 'unknown' : ($covered === $total ? 'full' : ($covered ? 'partial' : 'conflict')),
            'percentage' => $percentage, 'required_minutes' => $total, 'covered_minutes' => $covered];
    }
}
