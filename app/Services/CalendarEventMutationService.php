<?php

namespace App\Services;

use App\Http\Requests\DestroyCalendarEventRequest;
use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\BranchCalendarEvent;
use App\Models\CompanyCalendarEvent;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\TeamCalendarEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CalendarEventMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * @return array{id: int}
     */
    public function createCompanyEvent(StoreCalendarEventRequest $request, ?int $actorUserId = null): array
    {
        $organization = $this->resolveDefaultOrganization();

        $event = CompanyCalendarEvent::query()->create([
            'organization_id' => $organization->id,
            'category_id' => $request->categoryId(),
            'title' => $request->eventTitle(),
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', $request->startsAt()),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i', $request->endsAt()),
            'is_all_day' => $request->isAllDay(),
            'location' => $request->location(),
            'details' => $request->details(),
            'recurrence' => $request->recurrence(),
            'set_by_user_id' => $actorUserId,
            'last_edited_by_user_id' => null,
        ]);

        return ['id' => (int) $event->id];
    }

    /**
     * @return array{id: int}
     */
    public function createBranchEvent(StoreCalendarEventRequest $request, ?int $actorUserId = null): array
    {
        $organization = $this->resolveDefaultOrganization();
        $rootUnit = $this->resolveSelectedBranchRoot();

        $event = BranchCalendarEvent::query()->create([
            'organization_id' => $organization->id,
            'root_unit_id' => $rootUnit->id,
            'category_id' => $request->categoryId(),
            'title' => $request->eventTitle(),
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', $request->startsAt()),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i', $request->endsAt()),
            'is_all_day' => $request->isAllDay(),
            'location' => $request->location(),
            'details' => $request->details(),
            'recurrence' => $request->recurrence(),
            'set_by_user_id' => $actorUserId,
            'last_edited_by_user_id' => null,
        ]);

        return ['id' => (int) $event->id];
    }

    /**
     * @return array{id: int}
     */
    public function createTeamEvent(StoreCalendarEventRequest $request, ?int $actorUserId = null): array
    {
        $organization = $this->resolveDefaultOrganization();
        $rootUnit = $this->resolveSelectedBranchRoot();
        $teamUnitId = $request->teamUnitId();
        if ($teamUnitId !== null && ! $this->isUnitWithinRoot($teamUnitId, (int) $rootUnit->id)) {
            throw ValidationException::withMessages([
                'unit_id' => 'Selected team unit is outside the current branch context.',
            ]);
        }

        $event = TeamCalendarEvent::query()->create([
            'organization_id' => $organization->id,
            'root_unit_id' => $teamUnitId === null ? null : $rootUnit->id,
            'unit_id' => $teamUnitId,
            'category_id' => $request->categoryId(),
            'title' => $request->eventTitle(),
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', $request->startsAt()),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i', $request->endsAt()),
            'is_all_day' => $request->isAllDay(),
            'location' => $request->location(),
            'details' => $request->details(),
            'recurrence' => $request->recurrence(),
            'set_by_user_id' => $actorUserId,
            'last_edited_by_user_id' => null,
        ]);

        return ['id' => (int) $event->id];
    }

    /**
     * @return array{id: int}
     */
    public function updateCompanyEvent(
        CompanyCalendarEvent $event,
        UpdateCalendarEventRequest $request,
        ?int $actorUserId = null,
    ): array {
        $organization = $this->resolveDefaultOrganization();
        if ((int) $event->organization_id !== (int) $organization->id) {
            throw (new ModelNotFoundException)->setModel(CompanyCalendarEvent::class);
        }

        $recurrenceExceptions = $request->recurrence() === null
            ? null
            : $this->removeSkipExceptions(
                $event->recurrence_exceptions,
                $request->restoreOccurrenceDates(),
            );

        $event->fill([
            'category_id' => $request->categoryId(),
            'title' => $request->eventTitle(),
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', $request->startsAt()),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i', $request->endsAt()),
            'is_all_day' => $request->isAllDay(),
            'location' => $request->location(),
            'details' => $request->details(),
            'recurrence' => $request->recurrence(),
            'last_edited_by_user_id' => $actorUserId,
            'recurrence_exceptions' => $recurrenceExceptions,
        ]);
        $event->save();

        return ['id' => (int) $event->id];
    }

    /**
     * @return array{id: int}
     */
    public function updateBranchEvent(
        BranchCalendarEvent $event,
        UpdateCalendarEventRequest $request,
        ?int $actorUserId = null,
    ): array {
        $organization = $this->resolveDefaultOrganization();
        $rootUnit = $this->resolveSelectedBranchRoot();
        if (
            (int) $event->organization_id !== (int) $organization->id
            || (int) $event->root_unit_id !== (int) $rootUnit->id
        ) {
            throw (new ModelNotFoundException)->setModel(BranchCalendarEvent::class);
        }

        $recurrenceExceptions = $request->recurrence() === null
            ? null
            : $this->removeSkipExceptions(
                $event->recurrence_exceptions,
                $request->restoreOccurrenceDates(),
            );

        $event->fill([
            'category_id' => $request->categoryId(),
            'title' => $request->eventTitle(),
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', $request->startsAt()),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i', $request->endsAt()),
            'is_all_day' => $request->isAllDay(),
            'location' => $request->location(),
            'details' => $request->details(),
            'recurrence' => $request->recurrence(),
            'last_edited_by_user_id' => $actorUserId,
            'recurrence_exceptions' => $recurrenceExceptions,
        ]);
        $event->save();

        return ['id' => (int) $event->id];
    }

    /**
     * @return array{id: int}
     */
    public function updateTeamEvent(
        TeamCalendarEvent $event,
        UpdateCalendarEventRequest $request,
        ?int $actorUserId = null,
    ): array {
        $organization = $this->resolveDefaultOrganization();
        $rootUnit = $this->resolveSelectedBranchRoot();
        if (
            (int) $event->organization_id !== (int) $organization->id
            || (
                $event->root_unit_id !== null
                && (int) $event->root_unit_id !== (int) $rootUnit->id
            )
        ) {
            throw (new ModelNotFoundException)->setModel(TeamCalendarEvent::class);
        }

        $teamUnitId = $request->teamUnitId();
        if ($teamUnitId !== null && ! $this->isUnitWithinRoot($teamUnitId, (int) $rootUnit->id)) {
            throw ValidationException::withMessages([
                'unit_id' => 'Selected team unit is outside the current branch context.',
            ]);
        }

        $recurrenceExceptions = $request->recurrence() === null
            ? null
            : $this->removeSkipExceptions(
                $event->recurrence_exceptions,
                $request->restoreOccurrenceDates(),
            );

        $event->fill([
            'root_unit_id' => $teamUnitId === null ? null : $rootUnit->id,
            'unit_id' => $teamUnitId,
            'category_id' => $request->categoryId(),
            'title' => $request->eventTitle(),
            'starts_at' => Carbon::createFromFormat('Y-m-d H:i', $request->startsAt()),
            'ends_at' => Carbon::createFromFormat('Y-m-d H:i', $request->endsAt()),
            'is_all_day' => $request->isAllDay(),
            'location' => $request->location(),
            'details' => $request->details(),
            'recurrence' => $request->recurrence(),
            'last_edited_by_user_id' => $actorUserId,
            'recurrence_exceptions' => $recurrenceExceptions,
        ]);
        $event->save();

        return ['id' => (int) $event->id];
    }

    /**
     * @return array{id: int}
     */
    public function deleteCompanyEvent(CompanyCalendarEvent $event, DestroyCalendarEventRequest $request): array
    {
        $organization = $this->resolveDefaultOrganization();
        if ((int) $event->organization_id !== (int) $organization->id) {
            throw (new ModelNotFoundException)->setModel(CompanyCalendarEvent::class);
        }

        if ($request->applyTo() === 'single_occurrence') {
            $this->assertRecurringForSingleOccurrence($event->recurrence);
            $this->assertOccurrenceDateMatchesRecurrence(
                $event->recurrence,
                $request->occurrenceDate(),
                $event->starts_at,
            );
            $event->recurrence_exceptions = $this->upsertSkipException(
                $event->recurrence_exceptions,
                $request->occurrenceDate(),
            );
            $event->save();

            return ['id' => (int) $event->id];
        }

        $id = (int) $event->id;
        $event->deleteOrFail();

        return ['id' => $id];
    }

    /**
     * @return array{id: int}
     */
    public function deleteBranchEvent(BranchCalendarEvent $event, DestroyCalendarEventRequest $request): array
    {
        $organization = $this->resolveDefaultOrganization();
        $rootUnit = $this->resolveSelectedBranchRoot();
        if (
            (int) $event->organization_id !== (int) $organization->id
            || (int) $event->root_unit_id !== (int) $rootUnit->id
        ) {
            throw (new ModelNotFoundException)->setModel(BranchCalendarEvent::class);
        }

        if ($request->applyTo() === 'single_occurrence') {
            $this->assertRecurringForSingleOccurrence($event->recurrence);
            $this->assertOccurrenceDateMatchesRecurrence(
                $event->recurrence,
                $request->occurrenceDate(),
                $event->starts_at,
            );
            $event->recurrence_exceptions = $this->upsertSkipException(
                $event->recurrence_exceptions,
                $request->occurrenceDate(),
            );
            $event->save();

            return ['id' => (int) $event->id];
        }

        $id = (int) $event->id;
        $event->deleteOrFail();

        return ['id' => $id];
    }

    /**
     * @return array{id: int}
     */
    public function deleteTeamEvent(TeamCalendarEvent $event, DestroyCalendarEventRequest $request): array
    {
        $organization = $this->resolveDefaultOrganization();
        $rootUnit = $this->resolveSelectedBranchRoot();
        if (
            (int) $event->organization_id !== (int) $organization->id
            || (
                $event->root_unit_id !== null
                && (int) $event->root_unit_id !== (int) $rootUnit->id
            )
        ) {
            throw (new ModelNotFoundException)->setModel(TeamCalendarEvent::class);
        }

        if ($request->applyTo() === 'single_occurrence') {
            $this->assertRecurringForSingleOccurrence($event->recurrence);
            $this->assertOccurrenceDateMatchesRecurrence(
                $event->recurrence,
                $request->occurrenceDate(),
                $event->starts_at,
            );
            $event->recurrence_exceptions = $this->upsertSkipException(
                $event->recurrence_exceptions,
                $request->occurrenceDate(),
            );
            $event->save();

            return ['id' => (int) $event->id];
        }

        $id = (int) $event->id;
        $event->deleteOrFail();

        return ['id' => $id];
    }

    private function resolveDefaultOrganization(): Organization
    {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        return $organization;
    }

    private function resolveSelectedBranchRoot(): OrganizationalUnit
    {
        $branchId = (int) session(BranchContextService::SESSION_BRANCH_ID, 0);
        if ($branchId <= 0) {
            throw ValidationException::withMessages([
                'branch' => 'A branch context is required.',
            ]);
        }

        $organization = $this->resolveDefaultOrganization();
        $root = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereKey($branchId)
            ->first();
        if (! $root instanceof OrganizationalUnit) {
            throw ValidationException::withMessages([
                'branch' => 'Selected branch context is invalid.',
            ]);
        }

        return $root;
    }

    private function isUnitWithinRoot(int $unitId, int $rootUnitId): bool
    {
        if ($unitId === $rootUnitId) {
            return true;
        }

        $current = OrganizationalUnit::query()
            ->whereKey($unitId)
            ->where('is_active', true)
            ->first();

        while ($current instanceof OrganizationalUnit) {
            $parentId = $current->parent_id !== null ? (int) $current->parent_id : null;
            if ($parentId === null) {
                return false;
            }

            if ($parentId === $rootUnitId) {
                return true;
            }

            $current = OrganizationalUnit::query()
                ->whereKey($parentId)
                ->where('is_active', true)
                ->first();
        }

        return false;
    }

    private function assertRecurringForSingleOccurrence(mixed $recurrence): void
    {
        if (! is_array($recurrence)) {
            throw ValidationException::withMessages([
                'apply_to' => 'Single occurrence action requires a recurring event.',
            ]);
        }
    }

    private function assertOccurrenceDateMatchesRecurrence(
        mixed $recurrence,
        ?string $occurrenceDate,
        mixed $anchorStartsAt,
    ): void {
        if (! is_array($recurrence)) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Occurrence date is invalid for this series.',
            ]);
        }

        if (! is_string($occurrenceDate) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurrenceDate) !== 1) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Occurrence date is invalid for this series.',
            ]);
        }

        $anchor = $anchorStartsAt instanceof Carbon
            ? $anchorStartsAt->copy()
            : Carbon::parse((string) $anchorStartsAt);
        $target = Carbon::createFromFormat('Y-m-d', $occurrenceDate)->startOfDay();
        $anchorDay = $anchor->copy()->startOfDay();
        if ($target->lt($anchorDay)) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Occurrence date is invalid for this series.',
            ]);
        }

        $frequency = (string) ($recurrence['frequency'] ?? '');
        $interval = max(1, (int) ($recurrence['interval'] ?? 1));
        $ends = is_array($recurrence['ends'] ?? null) ? $recurrence['ends'] : ['type' => 'never'];
        $endsType = (string) ($ends['type'] ?? 'never');

        if ($endsType === 'until') {
            $untilRaw = (string) ($ends['date'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $untilRaw) === 1) {
                $until = Carbon::createFromFormat('Y-m-d', $untilRaw)->endOfDay();
                if ($target->gt($until)) {
                    throw ValidationException::withMessages([
                        'occurrence_date' => 'Occurrence date is invalid for this series.',
                    ]);
                }
            }
        }

        $matchesPattern = false;
        if ($frequency === 'daily') {
            $diffDays = $anchorDay->diffInDays($target);
            $matchesPattern = $diffDays % $interval === 0;
        } elseif ($frequency === 'weekly') {
            $weekdays = is_array($recurrence['byWeekday'] ?? null)
                ? array_map(fn ($day): int => (int) $day, $recurrence['byWeekday'])
                : [(int) $anchorDay->dayOfWeek];
            $weekdays = array_values(array_unique(array_filter(
                $weekdays,
                fn (int $day): bool => $day >= 0 && $day <= 6,
            )));
            if ($weekdays === []) {
                $weekdays = [(int) $anchorDay->dayOfWeek];
            }

            $anchorWeekStart = $anchorDay->copy()->startOfWeek(Carbon::MONDAY);
            $targetWeekStart = $target->copy()->startOfWeek(Carbon::MONDAY);
            $weeksDiff = (int) floor($anchorWeekStart->diffInDays($targetWeekStart) / 7);
            $matchesPattern = $weeksDiff >= 0
                && $weeksDiff % $interval === 0
                && in_array((int) $target->dayOfWeek, $weekdays, true);
        } elseif ($frequency === 'monthly') {
            $monthsDiff = (($target->year - $anchorDay->year) * 12) + ($target->month - $anchorDay->month);
            $matchesPattern = $monthsDiff >= 0
                && $monthsDiff % $interval === 0
                && $target->day === $anchorDay->day;
        } elseif ($frequency === 'yearly') {
            $yearsDiff = $target->year - $anchorDay->year;
            $matchesPattern = $yearsDiff >= 0
                && $yearsDiff % $interval === 0
                && $target->month === $anchorDay->month
                && $target->day === $anchorDay->day;
        }

        if (! $matchesPattern) {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Occurrence date is invalid for this series.',
            ]);
        }

        if ($endsType === 'count') {
            $count = max(1, (int) ($ends['count'] ?? 1));
            $ordinal = $this->occurrenceOrdinal($frequency, $interval, $anchorDay, $target, $recurrence);
            if ($ordinal < 1 || $ordinal > $count) {
                throw ValidationException::withMessages([
                    'occurrence_date' => 'Occurrence date is invalid for this series.',
                ]);
            }
        }
    }

    /**
     * Returns 1-based occurrence index for target day in a recurrence sequence.
     */
    private function occurrenceOrdinal(
        string $frequency,
        int $interval,
        Carbon $anchorDay,
        Carbon $target,
        array $recurrence,
    ): int {
        if ($frequency === 'daily') {
            return (int) floor($anchorDay->diffInDays($target) / $interval) + 1;
        }

        if ($frequency === 'monthly') {
            $monthsDiff = (($target->year - $anchorDay->year) * 12) + ($target->month - $anchorDay->month);

            return (int) floor($monthsDiff / $interval) + 1;
        }

        if ($frequency === 'yearly') {
            $yearsDiff = $target->year - $anchorDay->year;

            return (int) floor($yearsDiff / $interval) + 1;
        }

        if ($frequency === 'weekly') {
            $weekdays = is_array($recurrence['byWeekday'] ?? null)
                ? array_map(fn ($day): int => (int) $day, $recurrence['byWeekday'])
                : [(int) $anchorDay->dayOfWeek];
            $weekdays = array_values(array_unique(array_filter(
                $weekdays,
                fn (int $day): bool => $day >= 0 && $day <= 6,
            )));
            if ($weekdays === []) {
                $weekdays = [(int) $anchorDay->dayOfWeek];
            }
            sort($weekdays);

            $occurrenceCount = 0;
            $cursor = $anchorDay->copy();
            while ($cursor->lte($target)) {
                $weekStart = $cursor->copy()->startOfWeek(Carbon::MONDAY);
                $anchorWeekStart = $anchorDay->copy()->startOfWeek(Carbon::MONDAY);
                $weeksDiff = (int) floor($anchorWeekStart->diffInDays($weekStart) / 7);
                if ($weeksDiff >= 0 && $weeksDiff % $interval === 0 && in_array((int) $cursor->dayOfWeek, $weekdays, true)) {
                    $occurrenceCount += 1;
                }

                $cursor->addDay();
            }

            return $occurrenceCount;
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $exceptions
     * @return array<int, array<string, mixed>>
     */
    private function upsertSkipException(?array $exceptions, ?string $occurrenceDate): array
    {
        if (! is_string($occurrenceDate) || $occurrenceDate === '') {
            throw ValidationException::withMessages([
                'occurrence_date' => 'Occurrence date is required.',
            ]);
        }

        $rows = is_array($exceptions) ? $exceptions : [];
        $filtered = array_values(array_filter(
            $rows,
            fn (array $row): bool => (string) ($row['date'] ?? '') !== $occurrenceDate,
        ));
        $filtered[] = [
            'date' => $occurrenceDate,
            'action' => 'skip',
        ];

        return $filtered;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $exceptions
     * @param  list<string>  $restoreOccurrenceDates
     * @return array<int, array<string, mixed>>
     */
    private function removeSkipExceptions(?array $exceptions, array $restoreOccurrenceDates): array
    {
        $rows = is_array($exceptions) ? $exceptions : [];
        if ($restoreOccurrenceDates === []) {
            return $rows;
        }

        $restoreLookup = array_fill_keys($restoreOccurrenceDates, true);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => ! (
                ($row['action'] ?? null) === 'skip'
                && is_string($row['date'] ?? null)
                && isset($restoreLookup[$row['date']])
            ),
        ));
    }
}
