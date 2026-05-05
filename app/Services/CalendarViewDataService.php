<?php

namespace App\Services;

use App\Models\BranchCalendarEvent;
use App\Models\CalendarEventCategory;
use App\Models\CompanyCalendarEvent;
use App\Models\TeamCalendarEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CalendarViewDataService
{
    private const MAX_OCCURRENCES_PER_SERIES = 500;

    /**
     * @return array{data: list<array<string, mixed>>, nextPage: int|null, hasMore: bool}
     */
    public function companyEventsPageForOrganization(
        int $organizationId,
        ?CarbonInterface $displayMonth,
        int $page,
        int $perPage,
        array $filters = [],
    ): array {
        $query = CompanyCalendarEvent::query()
            ->where('organization_id', $organizationId)
            ->with([
                'category:id,name',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->orderBy('starts_at', 'asc')
            ->orderBy('id', 'asc');

        $this->applyListQueryWindow($query, $filters, $displayMonth);

        return $this->paginateExpandedEventsFromQuery($query, 'company', $page, $perPage, $filters, $displayMonth);
    }

    /**
     * @return array{data: list<array<string, mixed>>, nextPage: int|null, hasMore: bool}
     */
    public function branchEventsPageForRoot(
        int $organizationId,
        int $rootUnitId,
        ?CarbonInterface $displayMonth,
        int $page,
        int $perPage,
        array $filters = [],
    ): array {
        $query = BranchCalendarEvent::query()
            ->where('organization_id', $organizationId)
            ->where('root_unit_id', $rootUnitId)
            ->with([
                'category:id,name',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->orderBy('starts_at', 'asc')
            ->orderBy('id', 'asc');

        $this->applyListQueryWindow($query, $filters, $displayMonth);

        return $this->paginateExpandedEventsFromQuery($query, 'branch', $page, $perPage, $filters, $displayMonth);
    }

    /**
     * @param  list<int>  $unitIds
     * @return array{data: list<array<string, mixed>>, nextPage: int|null, hasMore: bool}
     */
    public function teamEventsPageForUnits(
        int $organizationId,
        int $rootUnitId,
        array $unitIds,
        bool $includeOrganizationScope,
        ?CarbonInterface $displayMonth,
        int $page,
        int $perPage,
        array $filters = [],
    ): array {
        if ($unitIds === [] && ! $includeOrganizationScope) {
            return [
                'data' => [],
                'nextPage' => null,
                'hasMore' => false,
            ];
        }

        $query = TeamCalendarEvent::query()
            ->where('organization_id', $organizationId)
            ->where(function (Builder $query) use ($rootUnitId, $unitIds, $includeOrganizationScope): void {
                if ($unitIds !== []) {
                    $query->where(function (Builder $unitScope) use ($rootUnitId, $unitIds): void {
                        $unitScope->where('root_unit_id', $rootUnitId)
                            ->whereIn('unit_id', $unitIds, 'and', false);
                    });
                }

                if ($includeOrganizationScope) {
                    if ($unitIds !== []) {
                        $query->orWhere(function (Builder $orgScope): void {
                            $orgScope->whereNull('root_unit_id', 'and', false)
                                ->whereNull('unit_id', 'and', false);
                        });
                    } else {
                        $query->whereNull('root_unit_id', 'and', false)
                            ->whereNull('unit_id', 'and', false);
                    }
                }
            })
            ->with([
                'category:id,name',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->orderBy('starts_at', 'asc')
            ->orderBy('id', 'asc');

        $this->applyListQueryWindow($query, $filters, $displayMonth);

        return $this->paginateExpandedEventsFromQuery($query, 'team', $page, $perPage, $filters, $displayMonth);
    }

    /**
     * @return list<array{id: int, name: string, colorKey: string}>
     */
    public function categoriesForOrganization(int $organizationId): array
    {
        return CalendarEventCategory::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'color_key'])
            ->map(fn (CalendarEventCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'colorKey' => $this->normalizeColorKey((string) $category->color_key),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companyEventsForOrganization(int $organizationId, ?CarbonInterface $displayMonth = null): array
    {
        $query = CompanyCalendarEvent::query()
            ->where('organization_id', $organizationId)
            ->with([
                'category:id,name',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->orderBy('starts_at', 'asc');

        $this->applyVisibleMonthWindow($query, $displayMonth);

        return $query->get()
            ->map(fn (CompanyCalendarEvent $event): array => $this->toCalendarEventPayload('company', $event))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function branchEventsForRoot(
        int $organizationId,
        int $rootUnitId,
        ?CarbonInterface $displayMonth = null,
    ): array {
        $query = BranchCalendarEvent::query()
            ->where('organization_id', $organizationId)
            ->where('root_unit_id', $rootUnitId)
            ->with([
                'category:id,name',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->orderBy('starts_at', 'asc');

        $this->applyVisibleMonthWindow($query, $displayMonth);

        return $query->get()
            ->map(fn (BranchCalendarEvent $event): array => $this->toCalendarEventPayload('branch', $event))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<array<string, mixed>>
     */
    public function teamEventsForUnits(
        int $organizationId,
        int $rootUnitId,
        array $unitIds,
        bool $includeOrganizationScope = false,
        ?CarbonInterface $displayMonth = null,
    ): array {
        if ($unitIds === [] && ! $includeOrganizationScope) {
            return [];
        }

        $query = TeamCalendarEvent::query()
            ->where('organization_id', $organizationId)
            ->where(function (Builder $query) use ($rootUnitId, $unitIds, $includeOrganizationScope): void {
                if ($unitIds !== []) {
                    $query->where(function (Builder $unitScope) use ($rootUnitId, $unitIds): void {
                        $unitScope->where('root_unit_id', $rootUnitId)
                            ->whereIn('unit_id', $unitIds, 'and', false);
                    });
                }

                if ($includeOrganizationScope) {
                    if ($unitIds !== []) {
                        $query->orWhere(function (Builder $orgScope): void {
                            $orgScope->whereNull('root_unit_id', 'and', false)
                                ->whereNull('unit_id', 'and', false);
                        });
                    } else {
                        $query->whereNull('root_unit_id', 'and', false)
                            ->whereNull('unit_id', 'and', false);
                    }
                }
            })
            ->with([
                'category:id,name',
                'setByUser:id,name',
                'lastEditedByUser:id,name',
            ])
            ->orderBy('starts_at', 'asc');

        $this->applyVisibleMonthWindow($query, $displayMonth);

        return $query->get()
            ->map(fn (TeamCalendarEvent $event): array => $this->toCalendarEventPayload('team', $event))
            ->values()
            ->all();
    }

    /**
     * @param  CompanyCalendarEvent|BranchCalendarEvent|TeamCalendarEvent  $event
     * @return array<string, mixed>
     */
    private function toCalendarEventPayload(string $prefix, object $event): array
    {
        /** @var array<string, mixed>|null $recurrence */
        $recurrence = is_array($event->recurrence) ? $event->recurrence : null;
        /** @var array<int, array<string, mixed>>|null $recurrenceExceptions */
        $recurrenceExceptions = is_array($event->recurrence_exceptions) ? $event->recurrence_exceptions : null;

        $payload = [
            'id' => $prefix.'-'.$event->id,
            'title' => (string) $event->title,
            'startsAt' => $this->formatEventDate($event->starts_at),
            'endsAt' => $this->formatEventDate($event->ends_at),
            'location' => is_string($event->location) ? $event->location : '',
            'category' => (string) ($event->category?->name ?? ''),
            'categoryId' => $event->category_id !== null ? (int) $event->category_id : null,
            'details' => is_string($event->details) ? $event->details : '',
            'allDay' => (bool) $event->is_all_day,
            'recurrence' => $recurrence,
            'recurrenceExceptions' => $recurrenceExceptions,
            'setBy' => $event->setByUser?->name,
            'lastEditedBy' => $event->lastEditedByUser?->name,
        ];

        if ($event instanceof TeamCalendarEvent) {
            $payload['unitId'] = $event->unit_id !== null ? (int) $event->unit_id : null;
        }

        return $payload;
    }

    private function formatEventDate(mixed $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('Y-m-d H:i');
        }

        return (string) $date;
    }

    private function normalizeColorKey(string $colorKey): string
    {
        $normalized = strtolower(trim($colorKey));
        $allowed = ['teal', 'blue', 'indigo', 'violet', 'fuchsia', 'rose', 'orange', 'emerald', 'slate'];

        return in_array($normalized, $allowed, true) ? $normalized : 'slate';
    }

    private function applyVisibleMonthWindow(Builder $query, ?CarbonInterface $displayMonth): void
    {
        if (! $displayMonth instanceof CarbonInterface) {
            return;
        }

        [$windowStart, $windowEndExclusive] = $this->visibleGridBoundsForMonth($displayMonth);
        $windowStartSql = $windowStart->toDateTimeString();
        $windowEndExclusiveSql = $windowEndExclusive->toDateTimeString();

        $query->where(function (Builder $scope) use ($windowStartSql, $windowEndExclusiveSql): void {
            $scope->where(function (Builder $overlap) use ($windowStartSql, $windowEndExclusiveSql): void {
                $overlap->where('starts_at', '<', $windowEndExclusiveSql)
                    ->where('ends_at', '>=', $windowStartSql);
            })->orWhere(function (Builder $recurrenceScope) use ($windowEndExclusiveSql): void {
                $recurrenceScope->whereNotNull('recurrence')
                    ->where('starts_at', '<', $windowEndExclusiveSql);
            });
        });
    }

    /**
     * @param  array{search?: string, categoryIds?: list<int>, range?: string, customFrom?: string|null, customTo?: string|null}  $filters
     */
    private function applyListQueryWindow(Builder $query, array $filters, ?CarbonInterface $displayMonth): void
    {
        $range = (string) ($filters['range'] ?? 'month');
        $customFrom = is_string($filters['customFrom'] ?? null) ? (string) $filters['customFrom'] : null;
        $customTo = is_string($filters['customTo'] ?? null) ? (string) $filters['customTo'] : null;
        [$start, $endExclusive] = $this->resolveRangeBounds($range, $customFrom, $customTo, $displayMonth);

        if (! $start instanceof CarbonInterface || ! $endExclusive instanceof CarbonInterface) {
            $this->applyVisibleMonthWindow($query, $displayMonth);

            return;
        }

        $startSql = $start->toDateTimeString();
        $endSql = $endExclusive->toDateTimeString();
        $query->where(function (Builder $scope) use ($startSql, $endSql): void {
            $scope->where(function (Builder $overlap) use ($startSql, $endSql): void {
                $overlap->where('starts_at', '<', $endSql)
                    ->where('ends_at', '>=', $startSql);
            })->orWhere(function (Builder $recurrenceScope) use ($endSql): void {
                $recurrenceScope->whereNotNull('recurrence')
                    ->where('starts_at', '<', $endSql);
            });
        });
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
     * @param  array{search?: string, categoryIds?: list<int>, range?: string, customFrom?: string|null, customTo?: string|null}  $filters
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyListFiltersToExpandedEvents(array $rows, array $filters): array
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $categoryIds = array_values(array_filter(
            array_map(fn ($id): int => (int) $id, (array) ($filters['categoryIds'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        return array_values(array_filter($rows, function (array $event) use ($search, $categoryIds): bool {
            if ($categoryIds !== []) {
                $categoryId = isset($event['categoryId']) ? (int) $event['categoryId'] : 0;
                if (! in_array($categoryId, $categoryIds, true)) {
                    return false;
                }
            }

            if ($search !== '') {
                $haystack = strtolower(
                    trim(implode(' ', [
                        (string) ($event['title'] ?? ''),
                        (string) ($event['location'] ?? ''),
                        (string) ($event['details'] ?? ''),
                        (string) ($event['category'] ?? ''),
                    ])),
                );
                if ($haystack === '' || ! str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function resolveRangeBounds(
        string $range,
        ?string $customFrom,
        ?string $customTo,
        ?CarbonInterface $referenceMonth = null,
    ): array {
        $anchor = $referenceMonth instanceof CarbonInterface
            ? Carbon::parse($referenceMonth)->startOfMonth()
            : now()->startOfMonth();
        $today = now();
        if ($range === 'week') {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $endExclusive = $start->copy()->addDays(7);

            return [$start, $endExclusive];
        }

        if ($range === 'month') {
            $start = $anchor->copy()->startOfMonth()->startOfDay();
            $endExclusive = $start->copy()->addMonth();

            return [$start, $endExclusive];
        }

        if ($range === 'year') {
            $start = $anchor->copy()->startOfYear()->startOfDay();
            $endExclusive = $start->copy()->addYear();

            return [$start, $endExclusive];
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

        $start = $today->copy()->startOfDay();
        $endExclusive = $start->copy()->addDay();

        return [$start, $endExclusive];
    }

    /**
     * @return array{data: list<array<string, mixed>>, nextPage: int|null, hasMore: bool}
     */
    private function paginateMappedEvents(
        Builder $query,
        string $prefix,
        int $page,
        int $perPage,
    ): array {
        $safePage = max(1, $page);
        $safePerPage = max(1, min(200, $perPage));
        $rows = $query->forPage($safePage, $safePerPage + 1)->get();

        $hasMore = $rows->count() > $safePerPage;
        $slice = $hasMore ? $rows->slice(0, $safePerPage) : $rows;

        /** @var list<array<string, mixed>> $data */
        $data = $slice
            ->map(fn (CompanyCalendarEvent|BranchCalendarEvent|TeamCalendarEvent $event): array => $this->toCalendarEventPayload($prefix, $event))
            ->values()
            ->all();

        return [
            'data' => $data,
            'nextPage' => $hasMore ? $safePage + 1 : null,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @param  array{search?: string, categoryIds?: list<int>, range?: string, customFrom?: string|null, customTo?: string|null}  $filters
     * @return array{data: list<array<string, mixed>>, nextPage: int|null, hasMore: bool}
     */
    private function paginateExpandedEventsFromQuery(
        Builder $query,
        string $prefix,
        int $page,
        int $perPage,
        array $filters,
        ?CarbonInterface $displayMonth,
    ): array {
        $safePage = max(1, $page);
        $safePerPage = max(1, min(200, $perPage));

        $baseRows = $query
            ->get()
            ->map(fn (CompanyCalendarEvent|BranchCalendarEvent|TeamCalendarEvent $event): array => $this->toCalendarEventPayload($prefix, $event))
            ->values()
            ->all();

        $range = (string) ($filters['range'] ?? 'today');
        $customFrom = is_string($filters['customFrom'] ?? null) ? (string) $filters['customFrom'] : null;
        $customTo = is_string($filters['customTo'] ?? null) ? (string) $filters['customTo'] : null;
        [$start, $endExclusive] = $this->resolveRangeBounds($range, $customFrom, $customTo, $displayMonth);

        $expandedRows = $this->expandEventsForListRange($baseRows, $start, $endExclusive);
        $filteredRows = $this->applyListFiltersToExpandedEvents($expandedRows, $filters);
        usort($filteredRows, function (array $a, array $b): int {
            $startCmp = strcmp((string) ($a['startsAt'] ?? ''), (string) ($b['startsAt'] ?? ''));
            if ($startCmp !== 0) {
                return $startCmp;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        $offset = ($safePage - 1) * $safePerPage;
        $slice = array_slice($filteredRows, $offset, $safePerPage + 1);
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
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function expandEventsForListRange(
        array $events,
        ?CarbonInterface $rangeStart,
        ?CarbonInterface $rangeEndExclusive,
    ): array {
        if (! $rangeStart instanceof CarbonInterface || ! $rangeEndExclusive instanceof CarbonInterface) {
            return $events;
        }

        $out = [];
        foreach ($events as $event) {
            $anchorStart = $this->parseEventDate((string) ($event['startsAt'] ?? ''));
            $anchorEnd = $this->parseEventDate((string) ($event['endsAt'] ?? ''));
            if (! $anchorStart instanceof CarbonInterface || ! $anchorEnd instanceof CarbonInterface) {
                continue;
            }

            $recurrence = $event['recurrence'] ?? null;
            if (! is_array($recurrence)) {
                if ($anchorStart->gte($rangeStart) && $anchorStart->lt($rangeEndExclusive)) {
                    $out[] = $event;
                }

                continue;
            }

            $occurrenceDays = $this->recurrenceOccurrenceDaysInRange(
                $anchorStart,
                $recurrence,
                $rangeStart,
                $rangeEndExclusive,
            );
            $exceptions = is_array($event['recurrenceExceptions'] ?? null) ? $event['recurrenceExceptions'] : [];
            $exceptionByDate = [];
            foreach ($exceptions as $exception) {
                if (is_array($exception) && is_string($exception['date'] ?? null)) {
                    $exceptionByDate[$exception['date']] = $exception;
                }
            }

            foreach ($occurrenceDays as $day) {
                $dayKey = $day->format('Y-m-d');
                $exception = $exceptionByDate[$dayKey] ?? null;
                if (is_array($exception) && ($exception['action'] ?? null) === 'skip') {
                    continue;
                }

                $occurrence = $event;
                $occurrence['id'] = (string) ($event['id'] ?? '').'::'.$dayKey;
                $occurrence['seriesId'] = (string) ($event['id'] ?? '');
                if (($event['allDay'] ?? false) === true) {
                    $occurrence['startsAt'] = $dayKey.' 00:00';
                    $occurrence['endsAt'] = $dayKey.' 23:59';
                } else {
                    $start = $this->shiftStartPreservingDuration($anchorStart, $day);
                    $durationSeconds = max(0, $anchorEnd->diffInSeconds($anchorStart, false) * -1);
                    $end = $start->copy()->addSeconds($durationSeconds);
                    $occurrence['startsAt'] = $start->format('Y-m-d H:i');
                    $occurrence['endsAt'] = $end->format('Y-m-d H:i');
                }

                if (is_array($exception) && ($exception['action'] ?? null) === 'override') {
                    foreach (['title', 'startsAt', 'endsAt', 'location', 'details', 'category', 'categoryId', 'allDay'] as $field) {
                        if (array_key_exists($field, $exception)) {
                            $occurrence[$field] = $exception[$field];
                        }
                    }
                }

                $out[] = $occurrence;
            }
        }

        return $out;
    }

    private function parseEventDate(string $value): ?CarbonInterface
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        try {
            $timezone = (string) config('app.timezone', 'UTC');

            return Carbon::createFromFormat('Y-m-d H:i', $normalized, $timezone);
        } catch (\Throwable) {
            try {
                return Carbon::parse($normalized);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function shiftStartPreservingDuration(CarbonInterface $anchorStart, CarbonInterface $newDay): CarbonInterface
    {
        return Carbon::create(
            $newDay->year,
            $newDay->month,
            $newDay->day,
            $anchorStart->hour,
            $anchorStart->minute,
            $anchorStart->second,
            $anchorStart->timezone,
        );
    }

    /**
     * @param  array<string, mixed>  $recurrence
     * @return list<CarbonInterface>
     */
    private function recurrenceOccurrenceDaysInRange(
        CarbonInterface $anchorStart,
        array $recurrence,
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEndExclusive,
    ): array {
        $interval = max(1, (int) ($recurrence['interval'] ?? 1));
        $frequency = (string) ($recurrence['frequency'] ?? '');
        $ends = is_array($recurrence['ends'] ?? null) ? $recurrence['ends'] : ['type' => 'never'];
        $endsType = (string) ($ends['type'] ?? 'never');
        $until = null;
        if ($endsType === 'until' && is_string($ends['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $ends['date']) === 1) {
            $until = Carbon::createFromFormat('Y-m-d', (string) $ends['date'])->endOfDay();
        }
        $maxCount = $endsType === 'count' ? max(1, (int) ($ends['count'] ?? 1)) : null;

        $anchorDay = Carbon::create($anchorStart->year, $anchorStart->month, $anchorStart->day, 0, 0, 0, $anchorStart->timezone);
        $scanEndDay = $rangeEndExclusive->copy()->subSecond()->startOfDay();
        $occurrenceDays = [];
        $emitted = 0;

        if ($frequency === 'daily') {
            for ($k = 0; $k < self::MAX_OCCURRENCES_PER_SERIES; $k += 1) {
                $candidate = $anchorDay->copy()->addDays($k * $interval);
                if ($candidate->gt($scanEndDay)) {
                    break;
                }
                if ($until instanceof CarbonInterface && $candidate->gt($until)) {
                    break;
                }

                $emitted += 1;
                if ($maxCount !== null && $emitted > $maxCount) {
                    break;
                }
                if ($candidate->gte($rangeStart->copy()->startOfDay())) {
                    $occurrenceDays[] = $candidate;
                }
            }

            return $occurrenceDays;
        }

        if ($frequency === 'weekly') {
            $byWeekday = is_array($recurrence['byWeekday'] ?? null)
                ? array_values(array_unique(array_filter(array_map(fn ($d): int => (int) $d, $recurrence['byWeekday']), fn (int $d): bool => $d >= 0 && $d <= 6)))
                : [(int) $anchorDay->dayOfWeek];
            if ($byWeekday === []) {
                $byWeekday = [(int) $anchorDay->dayOfWeek];
            }
            sort($byWeekday);

            $cursor = $anchorDay->copy();
            while ($cursor->lte($scanEndDay) && $emitted < self::MAX_OCCURRENCES_PER_SERIES) {
                $anchorWeek = $anchorDay->copy()->startOfWeek(Carbon::MONDAY);
                $cursorWeek = $cursor->copy()->startOfWeek(Carbon::MONDAY);
                $weeksBetween = (int) floor($anchorWeek->diffInDays($cursorWeek) / 7);
                if (
                    $weeksBetween >= 0
                    && $weeksBetween % $interval === 0
                    && in_array((int) $cursor->dayOfWeek, $byWeekday, true)
                ) {
                    if ($until instanceof CarbonInterface && $cursor->gt($until)) {
                        break;
                    }
                    $emitted += 1;
                    if ($maxCount !== null && $emitted > $maxCount) {
                        break;
                    }
                    if ($cursor->gte($rangeStart->copy()->startOfDay())) {
                        $occurrenceDays[] = $cursor->copy();
                    }
                }

                $cursor->addDay();
            }

            return $occurrenceDays;
        }

        if ($frequency === 'monthly') {
            $dom = $anchorDay->day;
            for ($m = 0; $m < 4800 && $emitted < self::MAX_OCCURRENCES_PER_SERIES; $m += $interval) {
                $candidate = $anchorDay->copy()->addMonthsNoOverflow($m);
                if ($candidate->day !== $dom) {
                    continue;
                }
                if ($candidate->gt($scanEndDay)) {
                    break;
                }
                if ($until instanceof CarbonInterface && $candidate->gt($until)) {
                    break;
                }

                $emitted += 1;
                if ($maxCount !== null && $emitted > $maxCount) {
                    break;
                }
                if ($candidate->gte($rangeStart->copy()->startOfDay())) {
                    $occurrenceDays[] = $candidate;
                }
            }

            return $occurrenceDays;
        }

        if ($frequency === 'yearly') {
            $month = $anchorDay->month;
            $dom = $anchorDay->day;
            for ($y = 0; $y < 400 && $emitted < self::MAX_OCCURRENCES_PER_SERIES; $y += $interval) {
                $candidate = $anchorDay->copy()->addYearsNoOverflow($y);
                if ($candidate->month !== $month || $candidate->day !== $dom) {
                    continue;
                }
                if ($candidate->gt($scanEndDay)) {
                    break;
                }
                if ($until instanceof CarbonInterface && $candidate->gt($until)) {
                    break;
                }

                $emitted += 1;
                if ($maxCount !== null && $emitted > $maxCount) {
                    break;
                }
                if ($candidate->gte($rangeStart->copy()->startOfDay())) {
                    $occurrenceDays[] = $candidate;
                }
            }

            return $occurrenceDays;
        }

        return [];
    }
}
