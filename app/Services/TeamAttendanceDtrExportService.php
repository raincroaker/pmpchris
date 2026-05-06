<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Exports\DtrMockExport;
use App\Models\Employee;
use App\Models\EmployeeLeaveDay;
use App\Models\EmployeeOvertime;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationHoliday;
use App\Support\AttendanceTemplateScheduledNetHours;
use App\Support\OrganizationHolidayOccurrenceDates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TeamAttendanceDtrExportService
{
    public function __construct(
        private ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
        private HolidayViewDataService $holidayViewDataService,
    ) {}

    /**
     * @return list<DtrMockExport>
     */
    public function buildExportsForScope(
        int $organizationId,
        int $workspaceRootUnitId,
        string $mode,
        ?int $employeeId,
        ?int $unitId,
        string $dateFrom,
        string $dateTo,
    ): array {
        $employees = $mode === 'individual'
            ? $this->employeesForIndividualMode($organizationId, $workspaceRootUnitId, $employeeId)
            : $this->employeesForTeamMode($organizationId, $workspaceRootUnitId, $unitId);

        if ($employees->isEmpty()) {
            return [];
        }

        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();
        $holidayMap = $this->holidayDateMap($organizationId, $from, $to);
        $holidayRows = $this->holidayRowsForPeriod($organizationId, $from, $to);

        return $employees
            ->map(function (Employee $employee) use ($from, $to, $holidayMap, $holidayRows): DtrMockExport {
                return $this->buildEmployeeExport($employee, $from, $to, $holidayMap, $holidayRows);
            })
            ->all();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeesForIndividualMode(int $organizationId, int $workspaceRootUnitId, ?int $employeeId): Collection
    {
        if ($employeeId === null) {
            return collect();
        }

        return Employee::query()
            ->whereKey($employeeId)
            ->whereNull('employees.deleted_at')
            ->with($this->employeeEagerLoads())
            ->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeesForTeamMode(int $organizationId, int $workspaceRootUnitId, ?int $unitId): Collection
    {
        if ($unitId === null) {
            return collect();
        }

        $selectableUnitIds = $this->scheduleAssignmentAccessService
            ->teamHrFormSelectableUnitIds($organizationId, $workspaceRootUnitId);

        if (! in_array($unitId, $selectableUnitIds, true)) {
            return collect();
        }

        $today = now()->toDateString();

        return Employee::query()
            ->whereNull('employees.deleted_at', 'and', false)
            ->whereHas('assignments', function ($query) use ($unitId, $today): void {
                $query->where('organizational_unit_id', $unitId)
                    ->whereNull('deleted_at', 'and', false)
                    ->where(function ($scope) use ($today): void {
                        $scope->whereNull('end_date', 'and', false)
                            ->orWhereDate('end_date', '>=', $today);
                    });
            })
            ->with($this->employeeEagerLoads())
            ->get()
            ->sortBy(fn (Employee $e): string => mb_strtolower((string) $e->last_name).'|'.(string) $e->id_number)
            ->values();
    }

    /**
     * @return list<string|array{0:string,1:\Closure}>
     */
    private function employeeEagerLoads(): array
    {
        return [
            'workScheduleTemplate',
            'assignments' => fn ($query) => $query
                ->whereNull('deleted_at', 'and', false)
                ->with('organizationalUnit')
                ->orderByDesc('is_primary')
                ->orderBy('id'),
            'positions' => fn ($query) => $query
                ->whereNull('deleted_at', 'and', false)
                ->with('position')
                ->orderByDesc('is_primary')
                ->orderBy('id'),
        ];
    }

    /**
     * @param  array<string, bool>  $holidayDateMap
     * @param  list<array{date:string,name:string}>  $holidayRows
     */
    private function buildEmployeeExport(
        Employee $employee,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $holidayDateMap,
        array $holidayRows,
    ): DtrMockExport {
        $attendanceDays = $employee->employeeAttendanceDays()
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->with(['segments', 'workScheduleTemplate' => fn ($query) => $query->withTrashed()])
            ->get()
            ->keyBy(fn ($day) => $day->work_date->format('Y-m-d'));

        $approvedLeaveDays = EmployeeLeaveDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('leave_date', '>=', $from->toDateString(), 'and')
            ->whereDate('leave_date', '<=', $to->toDateString(), 'and')
            ->whereHas('employeeLeave', function ($query): void {
                $query->where('status', EmployeeHrRecordStatus::Approved);
            })
            ->pluck('leave_date')
            ->map(fn ($d): string => CarbonImmutable::parse((string) $d)->format('Y-m-d'))
            ->unique()
            ->flip()
            ->all();

        $approvedOvertimeRows = EmployeeOvertime::query()
            ->where('employee_id', $employee->id)
            ->whereDate('ot_date', '>=', $from->toDateString(), 'and')
            ->whereDate('ot_date', '<=', $to->toDateString(), 'and')
            ->where('status', EmployeeHrRecordStatus::Approved)
            ->with('overtimePolicy:id,code,name')
            ->orderBy('ot_date', 'asc')
            ->get();

        $approvedLeaveRows = $employee->employeeLeaves()
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->where('status', EmployeeHrRecordStatus::Approved)
            ->with('leavePolicy:id,code,name')
            ->orderBy('start_date')
            ->get();

        $approvedOvertimeDateMap = $approvedOvertimeRows
            ->map(fn (EmployeeOvertime $ot): string => $ot->ot_date->format('Y-m-d'))
            ->flip()
            ->all();

        $rows = collect();
        $absenceCount = 0;
        $leaveCount = count($approvedLeaveDays);
        $lateCount = 0;
        $lateMinutes = 0;
        $earlyOutCount = 0;
        $earlyOutMinutes = 0;
        $grossMinutesTotal = 0;
        $netMinutesTotal = 0;
        $hasSchedule = $employee->workScheduleTemplate !== null;

        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $ymd = $d->format('Y-m-d');
            $day = $attendanceDays->get($ymd);
            $segments = $day?->segments ?? collect();
            $hasLogs = $segments->contains(fn ($seg) => ($seg->actual_in !== null && $seg->actual_in !== '') || ($seg->actual_out !== null && $seg->actual_out !== ''));
            $isHoliday = isset($holidayDateMap[$ymd]);
            $isOnLeave = isset($approvedLeaveDays[$ymd]);
            $isOvertime = isset($approvedOvertimeDateMap[$ymd]);
            $isRestDay = $this->isScheduledRestDay($employee, $d);

            if ($isHoliday && ! $hasLogs) {
                $rows->push($this->blankDayRow($d));

                continue;
            }

            if ($isRestDay && ! $hasLogs) {
                $rows->push($this->bandDayRow($d, 'REST DAY'));

                continue;
            }

            if (! $hasLogs && ! $isOnLeave) {
                $rows->push($this->bandDayRow($d, 'ABSENCE'));
                $absenceCount++;

                continue;
            }

            $isSinglePair = $this->isSinglePairSchedule($employee, $day);
            $row = $this->timeRowFromDay($d, $segments, $hasSchedule, $isSinglePair);
            if ($isOvertime && $hasLogs) {
                $row['remarks'] = 'OT';
            } elseif ($isOnLeave) {
                $row['remarks'] = 'OL';
            }

            if ($hasSchedule && $hasLogs) {
                $dayGross = $this->dayGrossMinutes($segments);
                $grossMinutesTotal += $dayGross;
                if ($day?->net_hours !== null) {
                    $netMinutesTotal += (int) round(((float) $day->net_hours) * 60);
                }
            }

            $rows->push($row);
        }

        $workScheduleReference = $this->workScheduleReferenceFor($employee);

        $unit = $this->resolveActualUnit($employee);
        $unitName = $unit?->name !== null ? (string) $unit->name : '—';
        $unitPath = $unit !== null ? implode(' - ', $this->unitPathNames($unit)) : $unitName;
        $position = $this->resolvePrimaryOrFirstPosition($employee);

        return new DtrMockExport(
            $this->displayEmployeeName($employee),
            (string) $employee->id_number,
            trim((string) ($employee->attendance_id ?? '')),
            $position,
            $unitName,
            $unitPath,
            $from->format('F Y'),
            DtrMockExport::formatShortDurationRange($from, $to),
            now()->format('Y-m-d H:i'),
            $absenceCount,
            $leaveCount,
            $lateCount,
            $lateMinutes,
            $earlyOutCount,
            $earlyOutMinutes,
            $hasSchedule ? $this->durationFromMinutes($grossMinutesTotal) : '—',
            $hasSchedule ? $this->durationFromMinutes($netMinutesTotal) : '—',
            $rows,
            $workScheduleReference,
            $approvedOvertimeRows->map(fn (EmployeeOvertime $ot): array => [
                'date' => $ot->ot_date->format('M j, Y'),
                'type' => trim(sprintf(
                    '%s%s',
                    (string) ($ot->overtimePolicy?->name ?? 'Approved overtime'),
                    ($ot->overtimePolicy?->code !== null && $ot->overtimePolicy->code !== '')
                        ? sprintf(' [%s]', (string) $ot->overtimePolicy->code)
                        : '',
                )),
            ])->values(),
            $approvedLeaveRows
                ->map(fn ($leave): array => [
                    'date' => sprintf(
                        '%s - %s',
                        $leave->start_date->format('M j, Y'),
                        $leave->end_date->format('M j, Y'),
                    ),
                    'type' => trim(sprintf(
                        '%s%s',
                        (string) ($leave->leavePolicy?->name ?? 'Approved leave'),
                        ($leave->leavePolicy?->code !== null && $leave->leavePolicy->code !== '')
                            ? sprintf(' [%s]', (string) $leave->leavePolicy->code)
                            : '',
                    )),
                ])
                ->values(),
            collect($holidayRows),
        );
    }

    /**
     * @return array<string, bool>
     */
    private function holidayDateMap(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rules = $this->holidayViewDataService
            ->organizationHolidayRulesIntersectingClosedRange($organizationId, $from->toDateString(), $to->toDateString());

        $dates = OrganizationHolidayOccurrenceDates::occurrencesInClosedRange(
            $rules,
            $from->toDateString(),
            $to->toDateString(),
        );

        return collect($dates)->flip()->map(fn () => true)->all();
    }

    /**
     * @return list<array{date:string,name:string}>
     */
    private function holidayRowsForPeriod(int $organizationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = OrganizationHoliday::query()
            ->where('organization_id', $organizationId)
            ->whereDate('start_date', '<=', $to->toDateString(), 'and')
            ->whereDate('end_date', '>=', $from->toDateString(), 'and')
            ->with('holidayType:id,name')
            ->orderBy('start_date', 'asc')
            ->get(['holiday_type_id', 'name', 'start_date']);

        return $rows
            ->map(fn (OrganizationHoliday $holiday): array => [
                'date' => $holiday->start_date->format('M j, Y'),
                'name' => trim(sprintf(
                    '%s%s',
                    (string) $holiday->name,
                    ($holiday->holidayType?->name !== null && $holiday->holidayType->name !== '')
                        ? sprintf(' (%s)', (string) $holiday->holidayType->name)
                        : '',
                )),
            ])
            ->values()
            ->all();
    }

    private function isScheduledRestDay(Employee $employee, CarbonImmutable $date): bool
    {
        $template = $employee->workScheduleTemplate;
        if ($template === null || ! is_array($template->days)) {
            return false;
        }

        $dayMap = [
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
        ];
        $key = $dayMap[$date->dayOfWeekIso] ?? null;
        if ($key === null) {
            return false;
        }

        return ! in_array($key, $template->days, true);
    }

    /**
     * @param  Collection<int, mixed>  $segments
     * @return array{day:int,dayName:string,spanMiddle:?string,amIn:string,amOut:string,pmIn:string,pmOut:string,otIn:string,otOut:string,remarks:string,hours:string}
     */
    private function timeRowFromDay(
        CarbonImmutable $date,
        Collection $segments,
        bool $hasSchedule,
        bool $isSinglePair,
    ): array {
        $segments = $segments->values();

        $first = $segments->get(0);
        $second = $segments->get(1);
        $third = $segments->get(2);

        $hours = '';
        if ($hasSchedule) {
            $gross = $this->dayGrossMinutes($segments);
            $hours = $gross > 0 ? number_format($gross / 60, 2, '.', '') : '';
        }

        if ($isSinglePair) {
            $firstIn = $this->firstActualIn($segments);
            $lastOut = $this->lastActualOut($segments);

            return [
                'day' => (int) $date->format('d'),
                'dayName' => $date->format('D'),
                'spanMiddle' => null,
                'amIn' => $firstIn,
                'amOut' => '',
                'pmIn' => '',
                'pmOut' => $lastOut,
                'otIn' => '',
                'otOut' => '',
                'remarks' => '',
                'hours' => $hours,
            ];
        }

        return [
            'day' => (int) $date->format('d'),
            'dayName' => $date->format('D'),
            'spanMiddle' => null,
            'amIn' => is_object($first) ? (string) ($first->actual_in ?? '') : '',
            'amOut' => is_object($first) ? (string) ($first->actual_out ?? '') : '',
            'pmIn' => is_object($second) ? (string) ($second->actual_in ?? '') : '',
            'pmOut' => is_object($second) ? (string) ($second->actual_out ?? '') : '',
            'otIn' => is_object($third) ? (string) ($third->actual_in ?? '') : '',
            'otOut' => is_object($third) ? (string) ($third->actual_out ?? '') : '',
            'remarks' => '',
            'hours' => $hours,
        ];
    }

    private function isSinglePairSchedule(Employee $employee, mixed $day): bool
    {
        $clockPattern = $day?->workScheduleTemplate?->clock_pattern
            ?? $employee->workScheduleTemplate?->clock_pattern;

        return (string) $clockPattern?->value === 'single_pair';
    }

    /**
     * @param  Collection<int, mixed>  $segments
     */
    private function firstActualIn(Collection $segments): string
    {
        foreach ($segments as $segment) {
            if (! is_object($segment)) {
                continue;
            }

            $actualIn = trim((string) ($segment->actual_in ?? ''));
            if ($actualIn !== '') {
                return $actualIn;
            }
        }

        return '';
    }

    /**
     * @param  Collection<int, mixed>  $segments
     */
    private function lastActualOut(Collection $segments): string
    {
        for ($index = $segments->count() - 1; $index >= 0; $index--) {
            $segment = $segments->get($index);
            if (! is_object($segment)) {
                continue;
            }

            $actualOut = trim((string) ($segment->actual_out ?? ''));
            if ($actualOut !== '') {
                return $actualOut;
            }
        }

        return '';
    }

    /**
     * @param  Collection<int, mixed>  $segments
     */
    private function dayGrossMinutes(Collection $segments): int
    {
        return $segments->sum(function ($seg): int {
            if (! is_object($seg)) {
                return 0;
            }
            $in = $this->hmToMinutes((string) ($seg->actual_in ?? ''));
            $out = $this->hmToMinutes((string) ($seg->actual_out ?? ''));
            if ($in === null || $out === null || $out <= $in) {
                return 0;
            }

            return $out - $in;
        });
    }

    private function hmToMinutes(string $hm): ?int
    {
        if (! preg_match('/^(\d{2}):(\d{2})$/', trim($hm), $m)) {
            return null;
        }

        return ((int) $m[1] * 60) + (int) $m[2];
    }

    /**
     * @return array{day:int,dayName:string,spanMiddle:string,amIn:string,amOut:string,pmIn:string,pmOut:string,otIn:string,otOut:string,remarks:string,hours:string}
     */
    private function bandDayRow(CarbonImmutable $date, string $band): array
    {
        return [
            'day' => (int) $date->format('d'),
            'dayName' => $date->format('D'),
            'spanMiddle' => $band,
            'amIn' => '',
            'amOut' => '',
            'pmIn' => '',
            'pmOut' => '',
            'otIn' => '',
            'otOut' => '',
            'remarks' => '',
            'hours' => '',
        ];
    }

    /**
     * @return array{day:int,dayName:string,spanMiddle:?string,amIn:string,amOut:string,pmIn:string,pmOut:string,otIn:string,otOut:string,remarks:string,hours:string}
     */
    private function blankDayRow(CarbonImmutable $date): array
    {
        return [
            'day' => (int) $date->format('d'),
            'dayName' => $date->format('D'),
            'spanMiddle' => null,
            'amIn' => '',
            'amOut' => '',
            'pmIn' => '',
            'pmOut' => '',
            'otIn' => '',
            'otOut' => '',
            'remarks' => '',
            'hours' => '',
        ];
    }

    /**
     * @return array{
     *     template_name:string,
     *     is_overnight:bool,
     *     is_active:bool,
     *     scheduled_days:string,
     *     clock_pattern:string,
     *     expected_windows:string,
     *     gross_time:string,
     *     net_time:string,
     *     unpaid_break:string,
     *     late_grace_minutes:int,
     *     notes:?string
     * }
     */
    private function workScheduleReferenceFor(Employee $employee): array
    {
        $template = $employee->workScheduleTemplate;
        if ($template === null) {
            return [
                'template_name' => '—',
                'is_overnight' => false,
                'is_active' => false,
                'scheduled_days' => '—',
                'clock_pattern' => '—',
                'expected_windows' => '—',
                'gross_time' => '—',
                'net_time' => '—',
                'unpaid_break' => '—',
                'late_grace_minutes' => 0,
                'notes' => 'No assigned employee work schedule for this period. Expected schedule metrics are unavailable.',
            ];
        }

        $days = is_array($template->days) ? implode(', ', array_map('ucfirst', $template->days)) : '—';
        $windows = $this->expectedWindowsFromTemplate($template);
        $grossMin = $this->templateGrossMinutes($template);
        $netHours = AttendanceTemplateScheduledNetHours::fromTemplate($template);

        return [
            'template_name' => (string) $template->name,
            'is_overnight' => (bool) $template->is_overnight,
            'is_active' => (bool) $template->is_active,
            'scheduled_days' => $days,
            'clock_pattern' => str_replace('_', ' ', (string) $template->clock_pattern->value),
            'expected_windows' => $windows,
            'gross_time' => $this->durationFromMinutes($grossMin),
            'net_time' => $this->durationFromMinutes((int) round($netHours * 60)),
            'unpaid_break' => sprintf('%d min', (int) $template->unpaid_break_minutes),
            'late_grace_minutes' => (int) $template->grace_late_arrival_minutes,
            'notes' => $template->notes ? (string) $template->notes : null,
        ];
    }

    private function expectedWindowsFromTemplate($template): string
    {
        if ($template->clock_pattern->value === 'single_pair') {
            return sprintf('Shift: %s-%s', (string) $template->time_in, (string) $template->time_out);
        }

        if (! is_array($template->segments) || $template->segments === []) {
            return '—';
        }

        return collect($template->segments)
            ->filter(fn ($seg): bool => is_array($seg))
            ->map(function (array $seg, int $index): string {
                $label = (string) ($seg['label'] ?? 'Session '.($index + 1));
                $in = (string) ($seg['time_in'] ?? '');
                $out = (string) ($seg['time_out'] ?? '');

                return sprintf('%s: %s-%s', $label, $in, $out);
            })
            ->implode("\n");
    }

    private function templateGrossMinutes($template): int
    {
        if ($template->clock_pattern->value === 'single_pair') {
            $in = $this->hmToMinutes((string) $template->time_in);
            $out = $this->hmToMinutes((string) $template->time_out);
            if ($in === null || $out === null) {
                return 0;
            }

            if ((bool) $template->is_overnight) {
                return max(0, (24 * 60 - $in) + $out);
            }

            return max(0, $out - $in);
        }

        if (! is_array($template->segments)) {
            return 0;
        }

        return (int) collect($template->segments)->sum(function ($seg): int {
            if (! is_array($seg)) {
                return 0;
            }
            $in = $this->hmToMinutes((string) ($seg['time_in'] ?? ''));
            $out = $this->hmToMinutes((string) ($seg['time_out'] ?? ''));
            if ($in === null || $out === null || $out <= $in) {
                return 0;
            }

            return $out - $in;
        });
    }

    private function durationFromMinutes(int $minutes): string
    {
        $h = intdiv(max(0, $minutes), 60);
        $m = max(0, $minutes) % 60;

        return sprintf('%d:%02d', $h, $m);
    }

    private function resolveActualUnit(Employee $employee): ?OrganizationalUnit
    {
        $today = now()->toDateString();
        $assignment = $employee->assignments
            ->filter(function ($assignment) use ($today): bool {
                if (! is_object($assignment)) {
                    return false;
                }
                if ($assignment->end_date === null) {
                    return true;
                }

                return $assignment->end_date->format('Y-m-d') >= $today;
            })
            ->firstWhere('is_primary', true)
            ?? $employee->assignments
                ->filter(function ($assignment) use ($today): bool {
                    if (! is_object($assignment)) {
                        return false;
                    }
                    if ($assignment->end_date === null) {
                        return true;
                    }

                    return $assignment->end_date->format('Y-m-d') >= $today;
                })
                ->first();

        return $assignment?->organizationalUnit;
    }

    /**
     * @return list<string>
     */
    private function unitPathNames(OrganizationalUnit $unit): array
    {
        $names = [];
        $cursor = $unit;
        while ($cursor !== null) {
            $names[] = (string) $cursor->name;
            if ($cursor->relationLoaded('parent') && $cursor->parent !== null) {
                $cursor = $cursor->parent;

                continue;
            }

            $cursor = $cursor->parent()->first();
        }

        return array_reverse($names);
    }

    private function resolvePrimaryOrFirstPosition(Employee $employee): string
    {
        $today = now()->toDateString();
        $activePositions = $employee->positions->filter(function ($position) use ($today): bool {
            if (! is_object($position)) {
                return false;
            }

            if ($position->end_date === null) {
                return true;
            }

            return $position->end_date->format('Y-m-d') >= $today;
        });

        $position = $activePositions->firstWhere('is_primary', true)
            ?? $activePositions->first()
            ?? $employee->positions->firstWhere('is_primary', true)
            ?? $employee->positions->first();

        return $position?->position?->title ? (string) $position->position->title : '—';
    }

    private function displayEmployeeName(Employee $employee): string
    {
        $suffix = $employee->suffix ? ' '.$employee->suffix : '';
        $middleInitial = $employee->middle_name
            ? ' '.mb_strtoupper(mb_substr(trim((string) $employee->middle_name), 0, 1)).'.'
            : '';

        return trim(sprintf(
            '%s, %s%s%s',
            (string) $employee->last_name,
            (string) $employee->first_name,
            $middleInitial,
            $suffix,
        ));
    }
}
