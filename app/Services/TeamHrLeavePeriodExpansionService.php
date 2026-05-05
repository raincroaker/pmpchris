<?php

namespace App\Services;

use App\Models\WorkScheduleTemplate;
use App\Support\OrganizationHolidayOccurrenceDates;
use Carbon\CarbonImmutable;

final readonly class TeamHrLeavePeriodExpansionService
{
    public function __construct(
        private HolidayViewDataService $holidayViewDataService,
    ) {}

    /**
     * @return array{
     *     counted: list<array{ date: string, is_half_day: bool }>,
     *     skipped: list<array{ date: string, reason: string }>
     * }
     */
    public function expandClosedRangeUsingTemplate(
        WorkScheduleTemplate $template,
        int $organizationId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $fromDay = $from->startOfDay();
        $toDay = $to->startOfDay();
        if ($fromDay->gt($toDay)) {
            return ['counted' => [], 'skipped' => []];
        }

        /** @var list<string>|null */
        $rawDays = $template->days ?? null;
        if (! is_array($rawDays)) {
            $rawDays = [];
        }

        /** @var list<string> */
        $scheduleDayKeys = array_values(array_unique(array_filter(array_map(static function ($d): ?string {
            if (! is_string($d)) {
                return null;
            }

            $t = strtolower(trim($d));

            return $t !== '' ? $t : null;
        }, $rawDays))));

        if ($scheduleDayKeys === []) {
            return ['counted' => [], 'skipped' => []];
        }

        $fromYmd = $fromDay->format('Y-m-d');
        $toYmd = $toDay->format('Y-m-d');

        $rules = $this->holidayViewDataService->organizationHolidayRulesIntersectingClosedRange(
            $organizationId,
            $fromYmd,
            $toYmd,
        );
        $holidayIsoDates = OrganizationHolidayOccurrenceDates::occurrencesInClosedRange(
            $rules,
            $fromYmd,
            $toYmd,
        );

        /** @var array<string, bool> */
        $holidaySet = array_fill_keys($holidayIsoDates, true);

        $counted = [];
        $skipped = [];

        for ($cur = $fromDay; $cur->lte($toDay); $cur = $cur->addDay()) {
            $iso = $cur->format('Y-m-d');
            $isHoliday = isset($holidaySet[$iso]);
            $dowKey = self::scheduleDayKeyForCarbonImmutable($cur);
            $isScheduledDay = in_array($dowKey, $scheduleDayKeys, true);

            if ($isHoliday) {
                $skipped[] = ['date' => $iso, 'reason' => 'Holiday'];

                continue;
            }

            if (! $isScheduledDay) {
                $skipped[] = ['date' => $iso, 'reason' => 'Rest day'];

                continue;
            }

            $counted[] = ['date' => $iso, 'is_half_day' => false];
        }

        return ['counted' => $counted, 'skipped' => $skipped];
    }

    private static function scheduleDayKeyForCarbonImmutable(CarbonImmutable $day): string
    {
        return match ((int) $day->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => 'mon',
        };
    }
}
