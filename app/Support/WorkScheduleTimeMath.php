<?php

namespace App\Support;

final class WorkScheduleTimeMath
{
    /**
     * Minutes from midnight for HH:mm (24h).
     */
    public static function parseTimeToMinutes(string $value): ?int
    {
        if (! str_contains($value, ':')) {
            return null;
        }

        $parts = explode(':', $value, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $h = (int) $parts[0];
        $m = (int) $parts[1];
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return $h * 60 + $m;
    }

    /**
     * On-duty span for one pair (single session row); overnight crosses midnight.
     */
    public static function approximateScheduleDurationMinutes(
        string $timeIn,
        string $timeOut,
        bool $isOvernight,
    ): ?int {
        $start = self::parseTimeToMinutes($timeIn);
        $end = self::parseTimeToMinutes($timeOut);
        if ($start === null || $end === null) {
            return null;
        }

        if ($isOvernight || $end <= $start) {
            return 24 * 60 - $start + $end;
        }

        return $end - $start;
    }

    /**
     * Minutes between session end and next session start (same calendar day).
     */
    public static function interSessionGapMinutes(string $sessionEnd, string $nextSessionStart): ?int
    {
        $outM = self::parseTimeToMinutes($sessionEnd);
        $inM = self::parseTimeToMinutes($nextSessionStart);
        if ($outM === null || $inM === null) {
            return null;
        }
        if ($inM < $outM) {
            return null;
        }

        return $inM - $outM;
    }

    /**
     * @param  list<array{time_in: string, time_out: string, is_overnight?: bool}>  $segments
     */
    public static function splitInterSessionGapsTotalMinutes(array $segments): ?int
    {
        $total = 0;
        $count = count($segments);
        for ($i = 0; $i < $count - 1; $i++) {
            $gap = self::interSessionGapMinutes($segments[$i]['time_out'], $segments[$i + 1]['time_in']);
            if ($gap === null) {
                return null;
            }
            $total += $gap;
        }

        return $total;
    }

    /**
     * Gross minutes for split sessions (sum of session durations).
     *
     * @param  list<array{time_in: string, time_out: string, is_overnight?: bool}>  $segments
     */
    public static function splitSessionsGrossMinutes(array $segments): ?int
    {
        $sum = 0;
        foreach ($segments as $segment) {
            $overnight = (bool) ($segment['is_overnight'] ?? false);
            $d = self::approximateScheduleDurationMinutes(
                $segment['time_in'],
                $segment['time_out'],
                $overnight,
            );
            if ($d === null) {
                return null;
            }
            $sum += $d;
        }

        return $sum;
    }
}
