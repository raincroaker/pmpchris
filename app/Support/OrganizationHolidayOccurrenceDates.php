<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Mirrors {@see resources/js/components/calendar/holiday-rule-expansion.ts}
 * expansion for serialized organization holiday rows (Holiday Calendar API shape).
 */
final class OrganizationHolidayOccurrenceDates
{
    private const MAX_YEAR_SPAN = 120;

    /**
     * Every ISO calendar day {@code Y-m-d} covered by occurrences of {@code $rules}
     * that fall within [{@code $gridStartYmd},{@code $gridEndYmd}] inclusive.
     *
     * @param  list<array<string, mixed>>  $rules
     * @return list<string>
     */
    public static function occurrencesInClosedRange(array $rules, string $gridStartYmd, string $gridEndYmd): array
    {
        $gridStartKey = substr($gridStartYmd, 0, 10);
        $gridEndKey = substr($gridEndYmd, 0, 10);

        $gridFirst = CarbonImmutable::parse($gridStartKey)->startOfDay();
        $gridLast = CarbonImmutable::parse($gridEndKey)->startOfDay();
        if ($gridFirst->gt($gridLast)) {
            return [];
        }

        $gridEndYear = (int) $gridLast->year;

        $out = [];

        foreach ($rules as $rule) {
            $recurrence = is_array($rule['recurrence'] ?? null)
                ? $rule['recurrence']
                : null;

            $isYearly = is_array($recurrence)
                && ($recurrence['frequency'] ?? null) === 'yearly';

            if ($recurrence === null || ! $isYearly) {
                [$start, $end] = self::normalizeHolidayRuleDates($rule);
                foreach (self::eachIsoDayInClosedRange($start, $end) as $dayKey) {
                    if (self::compareIsoDay($dayKey, $gridStartKey) >= 0 && self::compareIsoDay($dayKey, $gridEndKey) <= 0) {
                        $out[] = $dayKey;
                    }
                }

                continue;
            }

            [$anchorStartRaw] = self::normalizeHolidayRuleDates($rule);
            $parts = explode('-', $anchorStartRaw);
            $anchorYearFromStart = isset($parts[0]) ? (int) $parts[0] : $gridEndYear;
            $anchorYear = $anchorYearFromStart > 0 ? $anchorYearFromStart : $gridEndYear;
            $interval = max(1, (int) ($recurrence['interval'] ?? 1));
            $ends = is_array($recurrence['ends'] ?? null)
                ? $recurrence['ends']
                : ['type' => 'never'];

            $yearlyIndex = 0;
            $yLoop = $anchorYear;

            while ($yLoop <= $gridEndYear + $interval && $yearlyIndex < self::MAX_YEAR_SPAN) {
                $endsType = (string) ($ends['type'] ?? 'never');
                if ($endsType === 'count' && isset($ends['count']) && $yearlyIndex >= (int) $ends['count']) {
                    break;
                }

                $firstDay = self::yearlyOccurrenceFirstDay($rule, $yLoop);
                if ($endsType === 'until' && isset($ends['date']) && self::compareIsoDay($firstDay, (string) $ends['date']) > 0) {
                    break;
                }

                foreach (self::expandRuleDaysForYear($rule, $yLoop) as $dayKey) {
                    if (self::compareIsoDay($dayKey, $gridStartKey) >= 0 && self::compareIsoDay($dayKey, $gridEndKey) <= 0) {
                        $out[] = $dayKey;
                    }
                }

                $yearlyIndex += 1;
                $yLoop += $interval;
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{0:string, 1:string}
     */
    private static function normalizeHolidayRuleDates(array $rule): array
    {
        if (! empty($rule['date'])) {
            $d = substr((string) $rule['date'], 0, 10);

            return [$d, $d];
        }

        $start = substr((string) ($rule['start_date'] ?? ''), 0, 10);
        $end = substr((string) ($rule['end_date'] ?? ''), 0, 10);

        return [$start, $end === '' ? $start : $end];
    }

    /**
     * @return list<string>
     */
    private static function eachIsoDayInClosedRange(string $startIso, string $endIso): array
    {
        $start = CarbonImmutable::parse(substr($startIso, 0, 10))->startOfDay();
        $end = CarbonImmutable::parse(substr($endIso, 0, 10))->startOfDay();
        if ($start->gt($end)) {
            return [];
        }

        $out = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $out[] = $d->format('Y-m-d');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private static function yearlyOccurrenceFirstDay(array $rule, int $year): string
    {
        [$start] = self::normalizeHolidayRuleDates($rule);
        $parts = explode('-', $start);
        $m = (int) ($parts[1] ?? 1);
        $day = (int) ($parts[2] ?? 1);

        return self::dateInYear($year, $m, $day)->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<string>
     */
    private static function expandRuleDaysForYear(array $rule, int $year): array
    {
        [$start, $end] = self::normalizeHolidayRuleDates($rule);
        $sp = explode('-', $start);
        $ep = explode('-', $end);
        $ms = (int) ($sp[1] ?? 1);
        $ds = (int) ($sp[2] ?? 1);
        $me = (int) ($ep[1] ?? 1);
        $de = (int) ($ep[2] ?? 1);

        $startD = self::dateInYear($year, $ms, $ds);
        $endD = self::dateInYear($year, $me, $de);

        return self::eachIsoDayInClosedRange(
            $startD->format('Y-m-d'),
            $endD->format('Y-m-d'),
        );
    }

    private static function dateInYear(int $year, int $month1to12, int $day): CarbonImmutable
    {
        $first = CarbonImmutable::create($year, $month1to12, 1)->startOfDay();
        $dim = $first->daysInMonth;
        $clamp = min(max($day, 1), $dim);

        return CarbonImmutable::create($year, $month1to12, $clamp)->startOfDay();
    }

    private static function compareIsoDay(string $a, string $b): int
    {
        return strcmp(substr($a, 0, 10), substr($b, 0, 10));
    }
}
