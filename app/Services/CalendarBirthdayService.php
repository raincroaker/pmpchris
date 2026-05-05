<?php

namespace App\Services;

use App\Enums\EmployeeBirthdayVisibility;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeePosition;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CalendarBirthdayService
{
    /**
     * Employees affiliated to the branch root with {@see EmployeeBirthdayVisibility::Branch}.
     *
     * @return list<array<string, mixed>>
     */
    public function branchBirthdaysForRoot(
        int $organizationId,
        int $branchRootUnitId,
        CarbonInterface $displayMonth,
    ): array {
        $today = now()->toDateString();

        $employeeIds = EmployeeAffiliation::query()
            ->where('organization_id', $organizationId)
            ->where('root_unit_id', $branchRootUnitId)
            ->whereNull('deleted_at')
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->birthdayPayloadsForEmployees(
            $employeeIds,
            EmployeeBirthdayVisibility::Branch,
            $displayMonth,
            'branch',
        );
    }

    /**
     * Team calendar scope: unit assignments and/or org subtree when viewing organization-wide team scope.
     *
     * @param  list<int>  $visibleUnitIds  Selected unit id(s); empty when viewing organization-wide team calendar only.
     * @param  list<int>  $subtreeUnitIds  Units under the selected branch root (including root).
     * @return list<array<string, mixed>>
     */
    public function teamBirthdaysForScope(
        int $organizationId,
        array $visibleUnitIds,
        bool $includeOrganizationScope,
        array $subtreeUnitIds,
        CarbonInterface $displayMonth,
    ): array {
        if ($visibleUnitIds === [] && ! $includeOrganizationScope) {
            return [];
        }

        $today = now()->toDateString();

        $employeeIds = [];

        if ($visibleUnitIds !== []) {
            $fromUnits = EmployeeAssignment::query()
                ->whereIn('organizational_unit_id', $visibleUnitIds)
                ->whereNull('deleted_at')
                ->where(function (Builder $query) use ($today): void {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $today);
                })
                ->pluck('employee_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $employeeIds = array_merge($employeeIds, $fromUnits);
        }

        if ($includeOrganizationScope) {
            if ($subtreeUnitIds !== []) {
                $subtreeEmployees = EmployeeAssignment::query()
                    ->whereIn('organizational_unit_id', $subtreeUnitIds)
                    ->whereNull('deleted_at')
                    ->where(function (Builder $query) use ($today): void {
                        $query->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    })
                    ->pluck('employee_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
                $employeeIds = array_merge($employeeIds, $subtreeEmployees);
            }

            $orgScoped = EmployeeAssignment::query()
                ->where('organization_id', $organizationId)
                ->whereNull('organizational_unit_id')
                ->whereNull('deleted_at')
                ->where(function (Builder $query) use ($today): void {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $today);
                })
                ->pluck('employee_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $employeeIds = array_merge($employeeIds, $orgScoped);
        }

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        return $this->birthdayPayloadsForEmployees(
            $employeeIds,
            EmployeeBirthdayVisibility::Team,
            $displayMonth,
            'team',
        );
    }

    /**
     * @param  list<int>  $employeeIds
     * @return list<array<string, mixed>>
     */
    private function birthdayPayloadsForEmployees(
        array $employeeIds,
        EmployeeBirthdayVisibility $requiredVisibility,
        CarbonInterface $displayMonth,
        string $prefix,
    ): array {
        if ($employeeIds === []) {
            return [];
        }

        [$gridStart, $gridEndExclusive] = $this->visibleGridBoundsForMonth($displayMonth);

        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->where('birthday_visibility', $requiredVisibility)
            ->whereNotNull('birthdate')
            ->get(['id', 'first_name', 'last_name', 'birthdate']);

        $today = now()->toDateString();
        /** @var array<int, string> $primaryTitlesByEmployeeId */
        $primaryTitlesByEmployeeId = $this->primaryPositionTitlesByEmployeeId(
            $employees->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            $today,
        );

        $payloads = [];

        foreach ($employees as $employee) {
            $birthdate = $employee->birthdate;
            if (! $birthdate instanceof CarbonInterface) {
                continue;
            }

            $occurrence = $this->birthdayOccurrenceInGridWindow($birthdate, $gridStart, $gridEndExclusive);
            if ($occurrence === null) {
                continue;
            }

            $name = trim(implode(' ', array_filter([
                (string) $employee->first_name,
                (string) $employee->last_name,
            ])));

            $dateStr = $occurrence->format('Y-m-d');
            $employeeId = (int) $employee->id;
            $payloads[] = [
                'id' => 'birthday-'.$prefix.'-'.$employee->id.'-'.$dateStr,
                'title' => $name !== '' ? "{$name}'s birthday" : 'Birthday',
                'startsAt' => $dateStr.' 00:00',
                'endsAt' => $dateStr.' 23:59',
                'location' => '',
                'category' => 'Birthday',
                'categoryId' => null,
                'details' => '',
                'allDay' => true,
                'recurrence' => null,
                'recurrenceExceptions' => null,
                'eventKind' => 'birthday',
                'employeeId' => $employeeId,
                'primaryPositionTitle' => $primaryTitlesByEmployeeId[$employeeId] ?? null,
            ];
        }

        usort($payloads, function (array $a, array $b): int {
            $startCmp = strcmp((string) $a['startsAt'], (string) $b['startsAt']);
            if ($startCmp !== 0) {
                return $startCmp;
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        return $payloads;
    }

    /**
     * Current primary position title per employee (best-effort when multiple rows exist).
     *
     * @param  list<int>  $employeeIds
     * @return array<int, string>
     */
    private function primaryPositionTitlesByEmployeeId(array $employeeIds, string $today): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rows = EmployeePosition::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('is_primary', true)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->with('position:id,title')
            ->orderByDesc('start_date')
            ->get(['employee_id', 'position_id']);

        /** @var array<int, string> $map */
        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->employee_id;
            if (isset($map[$id])) {
                continue;
            }

            $title = $row->position?->title;
            if (is_string($title) && trim($title) !== '') {
                $map[$id] = trim($title);
            }
        }

        return $map;
    }

    /**
     * Anniversary date (month/day) within the calendar grid window for the viewed month.
     */
    private function birthdayOccurrenceInGridWindow(
        CarbonInterface $birthdate,
        CarbonInterface $gridStart,
        CarbonInterface $gridEndExclusive,
    ): ?CarbonInterface {
        $month = (int) $birthdate->month;
        $day = (int) $birthdate->day;

        $startYear = (int) $gridStart->year;
        $endYear = (int) $gridEndExclusive->copy()->subSecond()->year;

        for ($year = $startYear; $year <= $endYear; $year++) {
            $occurrence = $this->safeBirthdayForYear($year, $month, $day);
            if ($occurrence === null) {
                continue;
            }

            if ($occurrence->gte($gridStart) && $occurrence->lt($gridEndExclusive)) {
                return $occurrence;
            }
        }

        return null;
    }

    private function safeBirthdayForYear(int $year, int $month, int $day): ?Carbon
    {
        try {
            $lastDay = (int) Carbon::create($year, $month, 1)->endOfMonth()->day;
            $safeDay = min($day, $lastDay);

            return Carbon::create($year, $month, $safeDay)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
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
}
