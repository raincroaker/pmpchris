<?php

namespace App\Support;

/**
 * Canonical HH:mm normalization for attendance segment fields (parity across validation and persistence).
 */
final class AttendanceScheduleHm
{
    public static function normalize(string $raw): string
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
}
