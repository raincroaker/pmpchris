<?php

namespace App\Services;

use App\Models\HolidayType;
use App\Models\OrganizationHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class HolidayViewDataService
{
    /**
     * @param  array{search?: string, typeIds?: list<string>, range?: string, customFrom?: string|null, customTo?: string|null}  $filters
     * @return array{data: list<array<string, mixed>>, nextPage: int|null, hasMore: bool}
     */
    public function organizationHolidaysPageForOrganization(
        int $organizationId,
        CarbonInterface $displayMonth,
        int $page,
        int $perPage,
        array $filters = [],
    ): array {
        $safePage = max(1, $page);
        $safePerPage = max(1, min(200, $perPage));

        $range = (string) ($filters['range'] ?? 'month');
        $customFrom = is_string($filters['customFrom'] ?? null) ? (string) $filters['customFrom'] : null;
        $customTo = is_string($filters['customTo'] ?? null) ? (string) $filters['customTo'] : null;
        [$rangeStart, $rangeEndExclusive] = $this->resolveRangeBounds($range, $customFrom, $customTo, $displayMonth);

        if (! $rangeStart instanceof CarbonInterface || ! $rangeEndExclusive instanceof CarbonInterface) {
            [$rangeStart, $rangeEndExclusive] = $this->visibleGridBoundsForMonth($displayMonth);
        }

        $rows = $this->organizationHolidayRulesIntersectingClosedRange(
            $organizationId,
            $rangeStart->toDateString(),
            $rangeEndExclusive->copy()->subDay()->toDateString(),
        );

        $typeNameBySlug = collect($this->holidayTypesForOrganization($organizationId))
            ->mapWithKeys(fn (array $type): array => [(string) $type['id'] => (string) $type['name']])
            ->all();
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $typeIds = array_values(array_filter(
            array_map(fn ($id): string => strtolower(trim((string) $id)), (array) ($filters['typeIds'] ?? [])),
            fn (string $id): bool => $id !== '',
        ));
        $allowedTypeIds = $typeIds !== [] ? array_flip($typeIds) : null;

        $filtered = array_values(array_filter($rows, function (array $row) use ($allowedTypeIds, $search, $typeNameBySlug): bool {
            $typeId = strtolower((string) ($row['type_id'] ?? ''));
            if (is_array($allowedTypeIds) && ! isset($allowedTypeIds[$typeId])) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $typeName = strtolower((string) ($typeNameBySlug[$row['type_id'] ?? ''] ?? ''));
            $haystack = strtolower(trim(implode(' ', [
                (string) ($row['name'] ?? ''),
                (string) ($row['start_date'] ?? ''),
                (string) ($row['end_date'] ?? ''),
                (string) ($row['notes'] ?? ''),
                $typeName,
            ])));

            return $haystack !== '' && str_contains($haystack, $search);
        }));

        usort($filtered, fn (array $a, array $b): int => [$a['start_date'] ?? '', $a['name'] ?? '', $a['id'] ?? 0] <=> [$b['start_date'] ?? '', $b['name'] ?? '', $b['id'] ?? 0]);

        $offset = ($safePage - 1) * $safePerPage;
        $slice = array_slice($filtered, $offset, $safePerPage + 1);
        $hasMore = count($slice) > $safePerPage;
        if ($hasMore) {
            array_pop($slice);
        }

        return [
            'data' => array_values($slice),
            'nextPage' => $hasMore ? $safePage + 1 : null,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function holidayTypesForOrganization(int $organizationId): array
    {
        return HolidayType::query()
            ->where('organization_id', $organizationId)
            ->orderByRaw("CASE kind WHEN 'builtin' THEN 0 ELSE 1 END", [])
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn (HolidayType $type) => $this->serializeHolidayType($type))
            ->all();
    }

    /**
     * Holiday rules whose fixed span overlaps {@code [$fromYmd,$toYmd]} (inclusive) or that use recurrence with an
     * anchor on/before {@code $toYmd} (exact expansion is computed client-side, same shape as Holidays page).
     *
     * @return list<array<string, mixed>>
     */
    public function organizationHolidayRulesIntersectingClosedRange(
        int $organizationId,
        string $fromYmd,
        string $toYmd,
    ): array {
        return OrganizationHoliday::query()
            ->where('organization_id', $organizationId)
            ->with([
                'holidayType',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->where(function ($query) use ($fromYmd, $toYmd): void {
                $query->where(function ($fixed) use ($fromYmd, $toYmd): void {
                    $fixed->whereDate('start_date', '<=', $toYmd)
                        ->whereDate('end_date', '>=', $fromYmd);
                })->orWhere(function ($rec) use ($toYmd): void {
                    $rec->whereNotNull('recurrence')
                        ->whereDate('start_date', '<=', $toYmd);
                });
            })
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (OrganizationHoliday $holiday): array => $this->serializeOrganizationHoliday($holiday))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function organizationHolidaysForOrganization(int $organizationId, CarbonInterface $displayMonth): array
    {
        [$windowStart, $windowEndExclusive] = $this->visibleGridBoundsForMonth($displayMonth);
        $windowStartDate = $windowStart->toDateString();
        $lastGridDay = $windowEndExclusive->copy()->subDay()->toDateString();
        $windowEndExclusiveDate = $windowEndExclusive->toDateString();

        return OrganizationHoliday::query()
            ->where('organization_id', $organizationId)
            ->with([
                'holidayType',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->where(function ($query) use ($windowStartDate, $lastGridDay, $windowEndExclusiveDate): void {
                $query->where(function ($overlap) use ($windowStartDate, $lastGridDay): void {
                    $overlap->whereDate('start_date', '<=', $lastGridDay)
                        ->whereDate('end_date', '>=', $windowStartDate);
                })->orWhere(function ($recurrenceScope) use ($windowEndExclusiveDate): void {
                    $recurrenceScope->whereNotNull('recurrence')
                        ->whereDate('start_date', '<', $windowEndExclusiveDate);
                });
            })
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (OrganizationHoliday $holiday) => $this->serializeOrganizationHoliday($holiday))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeHolidayType(HolidayType $type): array
    {
        return [
            'id' => (string) $type->slug,
            'name' => (string) $type->name,
            'kind' => (string) $type->kind,
            'colorKey' => $this->normalizeHolidayColorKey((string) $type->color_key),
            'pay_policy' => (string) $type->pay_policy,
            'custom_multiplier' => $type->custom_multiplier,
            'premiumNote' => $type->premium_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOrganizationHoliday(OrganizationHoliday $holiday): array
    {
        $typeSlug = $holiday->relationLoaded('holidayType') && $holiday->holidayType
            ? (string) $holiday->holidayType->slug
            : (string) HolidayType::query()->whereKey($holiday->holiday_type_id)->value('slug');

        return [
            'id' => (int) $holiday->id,
            'start_date' => $holiday->start_date->format('Y-m-d'),
            'end_date' => $holiday->end_date->format('Y-m-d'),
            'name' => (string) $holiday->name,
            'type_id' => $typeSlug,
            'recurrence' => $holiday->recurrence,
            'notes' => $holiday->notes,
            'setBy' => $holiday->setByUser?->name,
            'lastEditedBy' => $holiday->lastEditedByUser?->name,
        ];
    }

    private function normalizeHolidayColorKey(string $colorKey): string
    {
        $normalized = strtolower(trim($colorKey));
        $allowed = ['cyan', 'amber', 'lime', 'rose', 'violet', 'sky'];

        return in_array($normalized, $allowed, true) ? $normalized : 'sky';
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function visibleGridBoundsForMonth(CarbonInterface $displayMonth): array
    {
        $firstOfMonth = Carbon::parse($displayMonth)->startOfMonth()->startOfDay();
        $lastOfMonth = Carbon::parse($displayMonth)->endOfMonth()->startOfDay();

        $mondayOffset = $firstOfMonth->dayOfWeekIso - 1;
        $gridStart = $firstOfMonth->copy()->subDays($mondayOffset)->startOfDay();

        $daysUntilSunday = 7 - $lastOfMonth->dayOfWeekIso;
        $gridEndInclusive = $lastOfMonth->copy()->addDays($daysUntilSunday)->endOfDay();
        $gridEndExclusive = $gridEndInclusive->copy()->addSecond()->startOfDay();

        return [$gridStart, $gridEndExclusive];
    }

    /**
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function resolveRangeBounds(
        string $range,
        ?string $customFrom,
        ?string $customTo,
        CarbonInterface $displayMonth,
    ): array {
        $anchor = Carbon::parse($displayMonth)->startOfMonth();
        $today = now()->startOfDay();

        if ($range === 'week') {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

            return [$start, $start->copy()->addDays(7)];
        }

        if ($range === 'month') {
            $start = $anchor->copy()->startOfMonth()->startOfDay();

            return [$start, $start->copy()->addMonth()];
        }

        if ($range === 'year') {
            $start = $anchor->copy()->startOfYear()->startOfDay();

            return [$start, $start->copy()->addYear()];
        }

        if ($range === 'custom') {
            if (! is_string($customFrom) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $customFrom) !== 1) {
                return [null, null];
            }

            $start = Carbon::createFromFormat('Y-m-d', $customFrom)->startOfDay();
            $to = $customTo;
            if (! is_string($to) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
                $to = $customFrom;
            }

            $endExclusive = Carbon::createFromFormat('Y-m-d', $to)->addDay()->startOfDay();

            return [$start, $endExclusive];
        }

        return [$today, $today->copy()->addDay()];
    }
}
