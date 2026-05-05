<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Validates JSON blobs for {@see \App\Models\WorkScheduleTemplate} attendance / overtime rule panels.
 */
final class WorkScheduleTemplateRulesValidator
{
    private static function isIntInRange(mixed $value, int $min, int $max): bool
    {
        if (is_int($value)) {
            return $value >= $min && $value <= $max;
        }

        if (is_float($value)) {
            $i = (int) round($value);

            return abs($value - $i) < 0.001 && $i >= $min && $i <= $max;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $rules
     */
    public static function validateAttendanceRules(?array $rules, callable $fail): void
    {
        if ($rules === null) {
            return;
        }

        foreach (['graceUsesLimitEnabled', 'netRegularHoursCapEnabled', 'advancedOpen'] as $boolKey) {
            if (array_key_exists($boolKey, $rules) && ! is_bool($rules[$boolKey])) {
                $fail("attendance_rules.{$boolKey} must be a boolean.");
            }
        }

        if (array_key_exists('graceUsesPerMonth', $rules)) {
            if (! self::isIntInRange($rules['graceUsesPerMonth'], 0, 31)) {
                $fail('attendance_rules.graceUsesPerMonth must be an integer from 0 to 31.');
            }
        }

        if (array_key_exists('clockInRounding', $rules)) {
            $v = $rules['clockInRounding'];
            if (! is_string($v) || ! in_array($v, ['none', '5', '15', 'custom'], true)) {
                $fail('attendance_rules.clockInRounding must be one of: none, 5, 15, custom.');
            }
        }

        if (array_key_exists('clockInRoundingCustomMinutes', $rules)) {
            if (! self::isIntInRange($rules['clockInRoundingCustomMinutes'], 1, 24 * 60)) {
                $fail('attendance_rules.clockInRoundingCustomMinutes must be an integer from 1 to 1440.');
            }
        }

        if (array_key_exists('netRegularHoursCap', $rules)) {
            $v = $rules['netRegularHoursCap'];
            if (! is_numeric($v) || (float) $v < 0 || (float) $v > 24) {
                $fail('attendance_rules.netRegularHoursCap must be a number from 0 to 24.');
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $rules
     */
    public static function validateOvertimeRules(?array $rules, callable $fail): void
    {
        if ($rules === null) {
            return;
        }

        if (array_key_exists('otBlockEnabled', $rules) && ! is_bool($rules['otBlockEnabled'])) {
            $fail('overtime_rules.otBlockEnabled must be a boolean.');
        }

        foreach (['otIsOvernight', 'continuousAfterRegularNet'] as $boolKey) {
            if (array_key_exists($boolKey, $rules) && ! is_bool($rules[$boolKey])) {
                $fail("overtime_rules.{$boolKey} must be a boolean.");
            }
        }

        foreach (['otTimeIn', 'otTimeOut'] as $timeKey) {
            if (! array_key_exists($timeKey, $rules)) {
                continue;
            }
            $v = $rules[$timeKey];
            if (! is_string($v) || ! preg_match('/^\d{1,2}:\d{2}$/', $v)) {
                $fail("overtime_rules.{$timeKey} must match H:mm or HH:mm.");
            }
        }

        if (array_key_exists('otGraceMinutes', $rules)) {
            if (! self::isIntInRange($rules['otGraceMinutes'], 0, 24 * 60)) {
                $fail('overtime_rules.otGraceMinutes must be an integer from 0 to 1440.');
            }
        }

        if (Arr::get($rules, 'otBlockEnabled') === true) {
            foreach (['otTimeIn', 'otTimeOut'] as $timeKey) {
                $t = $rules[$timeKey] ?? null;
                if (! is_string($t) || trim($t) === '' || ! preg_match('/^\d{1,2}:\d{2}$/', $t)) {
                    $fail("overtime_rules.{$timeKey} is required when otBlockEnabled is true.");
                }
            }
        }
    }
}
