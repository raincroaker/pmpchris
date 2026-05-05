<?php

namespace App\Support;

/**
 * First-segment clock-in vs scheduled start with template grace (parity with Team Attendance Vue mocks).
 */
final class AttendancePunctualityResolver
{
    /**
     * @param  array{scheduled_in?: ?string, actual_in?: ?string}|null  $firstSegment
     * @return 'on_time'|'late'|null null → store NULL (presented as not_applicable)
     */
    public static function fromFirstSegmentClockIn(?array $firstSegment, int $graceLateArrivalMinutes): ?string
    {
        if ($firstSegment === null) {
            return null;
        }

        $scheduledRaw = $firstSegment['scheduled_in'] ?? null;
        $actualRaw = $firstSegment['actual_in'] ?? null;
        if (! is_string($scheduledRaw) || trim($scheduledRaw) === '') {
            return null;
        }

        if (! is_string($actualRaw) || trim($actualRaw) === '') {
            return null;
        }

        $schedMin = self::hmToMinutes(self::normalizeHm($scheduledRaw));
        $actMin = self::hmToMinutes(self::normalizeHm($actualRaw));
        if ($schedMin === null || $actMin === null) {
            return null;
        }

        $grace = max(0, $graceLateArrivalMinutes);
        $deadline = $schedMin + $grace;
        if ($deadline >= 24 * 60) {
            $deadline -= 24 * 60;
        }

        if ($actMin <= $deadline) {
            return 'on_time';
        }

        return 'late';
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

    private static function hmToMinutes(string $normalizedHm): ?int
    {
        if (preg_match('/^(\d{2}):(\d{2})$/', $normalizedHm, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
