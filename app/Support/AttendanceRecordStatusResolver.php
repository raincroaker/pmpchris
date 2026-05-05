<?php

namespace App\Support;

use App\Enums\AttendanceRecordStatus;

/**
 * Derives {@see AttendanceRecordStatus} from per-session actual punches (HH:mm or null).
 * Parity: {@code deriveTeamAttendanceRecordStatus} in {@code resources/js/pages/Attendance/teamAttendanceUi.ts}.
 */
final class AttendanceRecordStatusResolver
{
    /**
     * @param  list<array{actual_in?: ?string, actual_out?: ?string}>  $segments
     */
    public static function fromPunchSegments(array $segments): AttendanceRecordStatus
    {
        if ($segments === []) {
            return AttendanceRecordStatus::Incomplete;
        }

        foreach ($segments as $index => $seg) {
            $inP = self::hasPunch($seg['actual_in'] ?? null);
            $outP = self::hasPunch($seg['actual_out'] ?? null);

            if ($inP && $outP) {
                continue;
            }

            if ($inP && ! $outP) {
                return AttendanceRecordStatus::Ongoing;
            }

            if (! $inP && $outP) {
                return AttendanceRecordStatus::Incomplete;
            }

            $hadPrior = self::sliceHasAnyPunch($segments, 0, $index - 1);
            if ($hadPrior) {
                return AttendanceRecordStatus::Incomplete;
            }

            $laterHas = self::sliceHasAnyPunch($segments, $index + 1, count($segments) - 1);
            if ($laterHas) {
                return AttendanceRecordStatus::Incomplete;
            }

            return AttendanceRecordStatus::Incomplete;
        }

        return AttendanceRecordStatus::Complete;
    }

    private static function hasPunch(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return trim($value) !== '';
    }

    /**
     * @param  list<array{actual_in?: ?string, actual_out?: ?string}>  $segments
     */
    private static function sliceHasAnyPunch(array $segments, int $start, int $end): bool
    {
        if ($start > $end) {
            return false;
        }

        for ($i = $start; $i <= $end; $i++) {
            if (! isset($segments[$i])) {
                continue;
            }

            $seg = $segments[$i];
            if (self::hasPunch($seg['actual_in'] ?? null) || self::hasPunch($seg['actual_out'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
