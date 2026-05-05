<?php

namespace App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Per-day leave rows from a legacy min/max span (mirrors
 * `expandLegacyRowToLeaveDays` in `leaveDaysDraftUtils.ts`).
 */
final class EmployeeLeaveLegacyDayExpansion
{
    /**
     * @return list<array{leave_date: string, is_half_day: bool}>
     */
    public static function expandFromLegacySpan(
        string|DateTimeInterface $start,
        string|DateTimeInterface $end,
        bool $isHalfDayStart,
        bool $isHalfDayEnd,
    ): array {
        $startYmd = self::toYmd($start);
        $endYmd = self::toYmd($end);

        if ($startYmd > $endYmd) {
            return [];
        }

        if ($startYmd === $endYmd) {
            return [[
                'leave_date' => $startYmd,
                'is_half_day' => $isHalfDayStart || $isHalfDayEnd,
            ]];
        }

        $cur = new DateTimeImmutable($startYmd.' 12:00:00');
        $endDate = new DateTimeImmutable($endYmd.' 12:00:00');
        $out = [];

        while ($cur <= $endDate) {
            $iso = $cur->format('Y-m-d');
            $isFirst = $iso === $startYmd;
            $isLast = $iso === $endYmd;

            if ($isFirst) {
                $isHalf = $isHalfDayStart;
            } elseif ($isLast) {
                $isHalf = $isHalfDayEnd;
            } else {
                $isHalf = false;
            }

            $out[] = ['leave_date' => $iso, 'is_half_day' => $isHalf];
            $cur = $cur->add(new DateInterval('P1D'));
        }

        return $out;
    }

    private static function toYmd(string|DateTimeInterface $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr($value, 0, 10);
    }
}
