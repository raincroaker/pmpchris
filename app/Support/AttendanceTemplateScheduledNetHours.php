<?php

namespace App\Support;

use App\Enums\WorkScheduleClockPattern;
use App\Models\WorkScheduleTemplate;
use Database\Seeders\DevelopmentEmployeeAttendanceDaysSeeder;

/**
 * Scheduled (template) payable hours snapshot for {@see EmployeeAttendanceDay::$net_hours} when status is complete.
 * Mirrors logic in {@see DevelopmentEmployeeAttendanceDaysSeeder::netHoursForTemplate}.
 */
final class AttendanceTemplateScheduledNetHours
{
    /**
     * Net payable hours from the template snapshot: single pair = span minus unpaid break;
     * split = sum of session spans from {@see WorkScheduleTemplate::$segments}.
     */
    public static function fromTemplate(WorkScheduleTemplate $template): float
    {
        if ($template->clock_pattern === WorkScheduleClockPattern::SinglePair) {
            $span = self::singlePairScheduledSpanMinutes($template);
            $breakMins = max(0, (int) $template->unpaid_break_minutes);

            return round(max(0, $span - $breakMins) / 60, 2);
        }

        $segments = $template->segments;
        if (! is_array($segments) || $segments === []) {
            return 0.0;
        }

        $totalMin = 0;
        foreach (array_values($segments) as $seg) {
            if (! is_array($seg)) {
                continue;
            }

            $timeIn = isset($seg['time_in']) && is_string($seg['time_in']) ? $seg['time_in'] : '09:00';
            $timeOut = isset($seg['time_out']) && is_string($seg['time_out']) ? $seg['time_out'] : '17:00';
            $start = self::hmToMinutesFromNormalized(self::normalizeHm($timeIn));
            $end = self::hmToMinutesFromNormalized(self::normalizeHm($timeOut));
            if ($start === null || $end === null) {
                continue;
            }

            $totalMin += max(0, $end - $start);
        }

        return round($totalMin / 60, 2);
    }

    private static function singlePairScheduledSpanMinutes(WorkScheduleTemplate $template): int
    {
        $in = self::hmToMinutesFromNormalized(self::normalizeHm((string) $template->time_in));
        $out = self::hmToMinutesFromNormalized(self::normalizeHm((string) $template->time_out));
        if ($in === null || $out === null) {
            return 0;
        }

        if ($template->is_overnight) {
            return max(0, (24 * 60 - $in) + $out);
        }

        return max(0, $out - $in);
    }

    private static function normalizeHm(string $raw): string
    {
        $t = trim($raw);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m) !== 1) {
            return strlen($t) === 5 ? $t : '09:00';
        }

        $h = (int) $m[1];
        $mm = $m[2];
        if ($h < 0 || $h > 23) {
            return '09:00';
        }

        return sprintf('%02d:%s', $h, $mm);
    }

    private static function hmToMinutesFromNormalized(string $normalizedHm): ?int
    {
        if (preg_match('/^(\d{2}):(\d{2})$/', $normalizedHm, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
