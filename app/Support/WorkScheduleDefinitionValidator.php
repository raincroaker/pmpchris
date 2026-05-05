<?php

namespace App\Support;

use App\Enums\WorkScheduleClockPattern;
use Closure;

final class WorkScheduleDefinitionValidator
{
    /**
     * @param  Closure(string): void  $fail
     */
    public static function validate(array $data, Closure $fail): void
    {
        $patternRaw = $data['clock_pattern'] ?? null;
        if (! is_string($patternRaw)) {
            return;
        }

        $pattern = WorkScheduleClockPattern::tryFrom($patternRaw);
        if ($pattern === null) {
            return;
        }

        $unpaid = isset($data['unpaid_break_minutes']) ? (int) $data['unpaid_break_minutes'] : null;
        $grace = isset($data['grace_late_arrival_minutes']) ? (int) $data['grace_late_arrival_minutes'] : null;

        if ($unpaid !== null && $unpaid < 0) {
            $fail('Breaktime must be a whole number ≥ 0.');
        }

        if ($grace !== null && $grace < 0) {
            $fail('Late arrival grace must be a whole number ≥ 0.');
        }

        match ($pattern) {
            WorkScheduleClockPattern::SinglePair => self::validateSinglePair($data, $fail),
            WorkScheduleClockPattern::SplitSessions => self::validateSplitSessions($data, $fail),
        };
    }

    /**
     * @param  Closure(string): void  $fail
     */
    private static function validateSinglePair(array $data, Closure $fail): void
    {
        $segments = $data['segments'] ?? null;
        if ($segments !== null && $segments !== [] && $segments !== '') {
            $fail('Single Session templates must not include session rows.');
        }

        $timeIn = isset($data['time_in']) ? (string) $data['time_in'] : '';
        $timeOut = isset($data['time_out']) ? (string) $data['time_out'] : '';
        $isOvernight = ! empty($data['is_overnight']);

        if ($timeIn === '' || $timeOut === '') {
            $fail('Time in and time out are required.');

            return;
        }

        $start = WorkScheduleTimeMath::parseTimeToMinutes($timeIn);
        $end = WorkScheduleTimeMath::parseTimeToMinutes($timeOut);
        if ($start === null || $end === null) {
            $fail('Times must be valid HH:mm values.');

            return;
        }

        if (! $isOvernight && $end <= $start) {
            $fail('Time out must be after time in, or enable overnight shift.');

            return;
        }

        $gross = WorkScheduleTimeMath::approximateScheduleDurationMinutes($timeIn, $timeOut, $isOvernight);
        $unpaid = isset($data['unpaid_break_minutes']) ? (int) $data['unpaid_break_minutes'] : 0;

        if ($gross !== null && $unpaid > $gross) {
            $fail('Breaktime cannot exceed the gross on-duty span.');
        }
    }

    /**
     * @param  Closure(string): void  $fail
     */
    private static function validateSplitSessions(array $data, Closure $fail): void
    {
        $segments = $data['segments'] ?? null;
        if (! is_array($segments)) {
            $fail('Split Sessions require at least two same-day session rows. Breaktime (minutes) must equal the sum of gaps between consecutive sessions.');

            return;
        }

        $count = count($segments);
        if ($count < 2) {
            $fail('Split Sessions require at least two same-day session rows.');

            return;
        }

        if ($count > 8) {
            $fail('Split Sessions support at most eight session rows.');

            return;
        }

        foreach ($segments as $index => $segment) {
            $label = 'Session '.($index + 1);
            if (! is_array($segment)) {
                $fail("{$label}: invalid session data.");

                continue;
            }

            $ti = isset($segment['time_in']) ? (string) $segment['time_in'] : '';
            $to = isset($segment['time_out']) ? (string) $segment['time_out'] : '';
            $segOvernight = ! empty($segment['is_overnight']);

            if ($ti === '' || $to === '') {
                $fail("{$label}: set both time in and time out.");

                continue;
            }

            if ($segOvernight) {
                $fail("{$label}: turn off overnight for split sessions (same calendar day only).");

                continue;
            }

            $start = WorkScheduleTimeMath::parseTimeToMinutes($ti);
            $end = WorkScheduleTimeMath::parseTimeToMinutes($to);
            if ($start === null || $end === null) {
                $fail("{$label}: times must be valid HH:mm values.");

                continue;
            }

            if ($end <= $start) {
                $fail("{$label}: time out must be after time in.");
            }
        }

        $gross = WorkScheduleTimeMath::splitSessionsGrossMinutes($segments);
        $gapsTotal = WorkScheduleTimeMath::splitInterSessionGapsTotalMinutes($segments);

        if ($gapsTotal === null) {
            $fail('Each next time in must be at or after the previous time out.');

            return;
        }

        $unpaid = isset($data['unpaid_break_minutes']) ? (int) $data['unpaid_break_minutes'] : 0;

        if ($unpaid !== $gapsTotal) {
            $fail('Breaktime must equal the sum of gaps between sessions ('.$gapsTotal.' minutes).');
        }

        if ($gross !== null && $unpaid > $gross) {
            $fail('Breaktime cannot exceed the gross on-duty span.');
        }
    }
}
