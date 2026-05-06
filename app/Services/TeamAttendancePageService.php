<?php

namespace App\Services;

use App\Enums\AttendanceRecordStatus;
use App\Enums\EmployeeHrRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Http\Requests\IndexTeamAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeLeaveDay;
use App\Models\EmployeeOvertime;
use App\Support\EmployeeBranchDirectoryFilter;
use App\Support\TeamAttendanceRecordPresenter;
use App\Support\TeamHrEmployeeDirectoryExtras;
use App\Support\TeamHrEmployeeDisplay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class TeamAttendancePageService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
    ) {}

    /**
     * @return array{
     *     paginator: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array<string, mixed>,
     *     kpis: array<string, int>,
     *     kpiEmployees: array<string, list<array<string, mixed>>>,
     *     chart: array{half: string, rows: list<array<string, int|string>>}
     * }
     */
    public function buildPage(IndexTeamAttendanceRequest $request): array
    {
        $validated = $request->validated();
        $organization = $this->branchContextService->defaultOrganization();
        $workspace = $this->branchContextService->workspaceBranchContext($request);

        $page = (int) $validated['page'];
        $perPage = (int) $validated['per_page'];

        if ($organization === null || $workspace === null) {
            $filters = $request->inertiaTeamAttendanceFilters();
            $filters['unit_id'] = null;

            return [
                'paginator' => new LengthAwarePaginator([], 0, $perPage, $page, [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]),
                'filters' => $filters,
                'kpis' => [
                    'lates_today' => 0,
                    'absent_yesterday' => 0,
                    'on_leave_today' => 0,
                    'ot_yesterday' => 0,
                ],
                'kpiEmployees' => [
                    'lates_today' => [],
                    'absent_yesterday' => [],
                    'on_leave_today' => [],
                    'ot_yesterday' => [],
                ],
                'chart' => [
                    'half' => (string) ($filters['chart_half'] ?? 'first_half'),
                    'rows' => [],
                ],
            ];
        }

        $orgId = (int) $organization->id;
        $branchRootId = (int) $workspace['id'];
        $today = now()->toDateString();
        $selectableUnitIds = $this->scheduleAssignmentAccessService->teamHrFormSelectableUnitIds($orgId, $branchRootId);

        if (
            isset($validated['unit_id'])
            && $validated['unit_id'] !== null
            && ! in_array((int) $validated['unit_id'], $selectableUnitIds, true)
        ) {
            $validated['unit_id'] = null;
        }

        $query = $this->baseAttendanceDayQuery($orgId, $branchRootId, $today);
        $this->applyAttendanceFilters($query, $validated, $today);

        $paginator = $query
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForAttendancePresenters($today),
                'employee.user:id,employee_id,avatar_path',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'segments',
                'workScheduleTemplate' => static fn ($q) => $q->withTrashed(),
                'createdByUser' => static fn ($q) => $q->with('employee'),
                'updatedByUser' => static fn ($q) => $q->with('employee'),
            ])
            ->orderBy(
                'employee_attendance_days.work_date',
                ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            )
            ->orderByDesc('employee_attendance_days.id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->getCollection()->transform(static fn (EmployeeAttendanceDay $day): array => TeamAttendanceRecordPresenter::toPageRow($day));

        $filters = $request->inertiaTeamAttendanceFilters();
        $filters['unit_id'] = $validated['unit_id'] ?? null;

        $kpis = $this->buildKpis($orgId, $branchRootId, $today, $validated);
        $kpiEmployees = $this->buildKpiEmployees($orgId, $branchRootId, $today, $validated);
        $chart = $this->buildChart($orgId, $branchRootId, $today, $validated);

        return [
            'paginator' => $paginator,
            'filters' => $filters,
            'kpis' => $kpis,
            'kpiEmployees' => $kpiEmployees,
            'chart' => $chart,
        ];
    }

    /**
     * @return Builder<EmployeeAttendanceDay>
     */
    private function baseAttendanceDayQuery(int $organizationId, int $branchRootId, string $today): Builder
    {
        return EmployeeAttendanceDay::query()
            ->where('employee_attendance_days.organization_id', $organizationId)
            ->whereNull('employee_attendance_days.deleted_at', 'and', false)
            ->whereHas('employee', function ($query) use ($organizationId, $branchRootId, $today): void {
                $query->whereNull('employees.deleted_at');
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $today);
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAttendanceFilters(Builder $query, array $filters, string $today): void
    {
        $query->whereDate('employee_attendance_days.work_date', '>=', $filters['date_from'])
            ->whereDate('employee_attendance_days.work_date', '<=', $filters['date_to']);

        if (! empty($filters['unit_id'])) {
            $unitId = (int) $filters['unit_id'];
            $query->where(function (Builder $w) use ($unitId, $today): void {
                $w->where('employee_attendance_days.organizational_unit_id', $unitId)
                    ->orWhereHas('employee.assignments', function (Builder $a) use ($unitId, $today): void {
                        $a->where('employee_assignments.organizational_unit_id', $unitId)
                            ->whereNull('employee_assignments.deleted_at')
                            ->where(function ($q2) use ($today): void {
                                $q2->whereNull('employee_assignments.end_date')
                                    ->orWhereDate('employee_assignments.end_date', '>=', $today);
                            });
                    });
            });
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $query->where(
                'employee_attendance_days.status',
                AttendanceRecordStatus::from($status),
            );
        }

        $punctuality = (string) ($filters['punctuality'] ?? 'all');
        if ($punctuality === 'on_time') {
            $query->where('employee_attendance_days.punctuality', 'on_time');
        } elseif ($punctuality === 'late') {
            $query->where('employee_attendance_days.punctuality', 'late');
        } elseif ($punctuality === 'not_applicable') {
            $query->whereNull('employee_attendance_days.punctuality');
        }

        $recording = (string) ($filters['recording_style'] ?? 'all');
        if ($recording === 'simple') {
            $query->where('employee_attendance_days.clock_pattern', WorkScheduleClockPattern::SinglePair)
                ->where('employee_attendance_days.is_overnight_schedule', false);
        } elseif ($recording === 'split') {
            $query->where('employee_attendance_days.clock_pattern', WorkScheduleClockPattern::SplitSessions)
                ->where('employee_attendance_days.is_overnight_schedule', false);
        } elseif ($recording === 'overnight') {
            $query->where('employee_attendance_days.is_overnight_schedule', true);
        }

        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $w) use ($like): void {
                $w->whereHas('employee', function (Builder $e) use ($like): void {
                    $e->where('employees.id_number', 'like', $like)
                        ->orWhere('employees.first_name', 'like', $like)
                        ->orWhere('employees.last_name', 'like', $like)
                        ->orWhere('employees.middle_name', 'like', $like)
                        ->orWhere('employees.attendance_id', 'like', $like);
                })->orWhere('employee_attendance_days.ingest_key', 'like', $like);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{lates_today: int, absent_yesterday: int, on_leave_today: int, ot_yesterday: int}
     */
    private function buildKpis(int $organizationId, int $branchRootId, string $today, array $filters): array
    {
        $todayDate = CarbonImmutable::parse($today);
        $yesterdayDate = $todayDate->subDay();
        $todayIso = $todayDate->toDateString();
        $yesterdayIso = $yesterdayDate->toDateString();
        $unitId = isset($filters['unit_id']) && $filters['unit_id'] !== null ? (int) $filters['unit_id'] : null;

        $latesToday = $this->baseAttendanceDayQuery($organizationId, $branchRootId, $todayIso)
            ->when($unitId !== null, fn (Builder $query): Builder => $this->applyAttendanceUnitScope($query, $unitId, $todayIso))
            ->whereDate('employee_attendance_days.work_date', '=', $todayIso, 'and')
            ->where('employee_attendance_days.punctuality', 'late')
            ->count();

        $onLeaveToday = EmployeeLeaveDay::query()
            ->where('employee_leave_days.organization_id', $organizationId)
            ->whereDate('employee_leave_days.leave_date', '=', $todayIso, 'and')
            ->whereHas('employee', function (Builder $query) use ($organizationId, $branchRootId, $todayIso): void {
                $query->whereNull('employees.deleted_at');
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $todayIso);
            })
            ->whereHas('employeeLeave', function (Builder $query) use ($unitId): void {
                $query->whereNull('employee_leaves.deleted_at')
                    ->where('employee_leaves.status', EmployeeHrRecordStatus::Approved)
                    ->when($unitId !== null, fn (Builder $q): Builder => $q->where('employee_leaves.organizational_unit_id', $unitId));
            })
            ->distinct()
            ->count('employee_leave_days.employee_id');

        $otYesterday = EmployeeOvertime::query()
            ->where('employee_overtimes.organization_id', $organizationId)
            ->whereNull('employee_overtimes.deleted_at', 'and', false)
            ->whereDate('employee_overtimes.ot_date', '=', $yesterdayIso, 'and')
            ->where('employee_overtimes.status', EmployeeHrRecordStatus::Approved)
            ->whereHas('employee', function (Builder $query) use ($organizationId, $branchRootId, $todayIso): void {
                $query->whereNull('employees.deleted_at');
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $todayIso);
            })
            ->when($unitId !== null, fn (Builder $query): Builder => $query->where('employee_overtimes.organizational_unit_id', $unitId))
            ->count();

        $visibleEmployeeIds = Employee::query()
            ->whereNull('employees.deleted_at', 'and', false)
            ->where(function (Builder $query) use ($organizationId, $branchRootId, $yesterdayIso): void {
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $yesterdayIso);
            })
            ->when(
                $unitId !== null,
                function (Builder $query) use ($unitId, $yesterdayIso): Builder {
                    return $query->whereHas('assignments', function (Builder $assignmentQuery) use ($unitId, $yesterdayIso): void {
                        $assignmentQuery->where('employee_assignments.organizational_unit_id', $unitId)
                            ->whereNull('employee_assignments.deleted_at')
                            ->where(function (Builder $dateQuery) use ($yesterdayIso): void {
                                $dateQuery->whereNull('employee_assignments.end_date')
                                    ->orWhereDate('employee_assignments.end_date', '>=', $yesterdayIso);
                            });
                    });
                }
            )
            ->pluck('employees.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $attendanceYesterdayEmployeeIds = EmployeeAttendanceDay::query()
            ->where('organization_id', $organizationId)
            ->whereDate('work_date', '=', $yesterdayIso, 'and')
            ->whereNull('deleted_at', 'and', false)
            ->whereIn('employee_id', $visibleEmployeeIds, 'and', false)
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $approvedLeaveYesterdayEmployeeIds = EmployeeLeaveDay::query()
            ->where('organization_id', $organizationId)
            ->whereDate('leave_date', '=', $yesterdayIso, 'and')
            ->whereIn('employee_id', $visibleEmployeeIds, 'and', false)
            ->whereHas('employeeLeave', function (Builder $query) use ($unitId): void {
                $query->whereNull('employee_leaves.deleted_at')
                    ->where('employee_leaves.status', EmployeeHrRecordStatus::Approved)
                    ->when($unitId !== null, fn (Builder $q): Builder => $q->where('employee_leaves.organizational_unit_id', $unitId));
            })
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $absentYesterday = collect($visibleEmployeeIds)
            ->diff($attendanceYesterdayEmployeeIds)
            ->diff($approvedLeaveYesterdayEmployeeIds)
            ->count();

        return [
            'lates_today' => $latesToday,
            'absent_yesterday' => $absentYesterday,
            'on_leave_today' => $onLeaveToday,
            'ot_yesterday' => $otYesterday,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{half: string, rows: list<array{date: string, on_time: int, late: int, absent: int}>}
     */
    private function buildChart(int $organizationId, int $branchRootId, string $today, array $filters): array
    {
        $dateFrom = CarbonImmutable::parse((string) $filters['date_from']);
        $monthStart = $dateFrom->startOfMonth();
        $monthEnd = $dateFrom->endOfMonth();
        $half = (string) ($filters['chart_half'] ?? 'first_half');

        if ($half === 'second_half') {
            $periodStart = $monthStart->addDays(15);
            $periodEnd = $monthEnd;
        } else {
            $periodStart = $monthStart;
            $periodEnd = $monthStart->addDays(14);
        }

        $todayDate = CarbonImmutable::parse($today);
        $unitId = isset($filters['unit_id']) && $filters['unit_id'] !== null ? (int) $filters['unit_id'] : null;

        $rows = [];

        for ($cursor = $periodStart; $cursor->lte($periodEnd); $cursor = $cursor->addDay()) {
            $iso = $cursor->toDateString();

            $onTime = $this->baseAttendanceDayQuery($organizationId, $branchRootId, $todayDate->toDateString())
                ->when($unitId !== null, fn (Builder $query): Builder => $this->applyAttendanceUnitScope($query, $unitId, $iso))
                ->whereDate('employee_attendance_days.work_date', '=', $iso, 'and')
                ->where('employee_attendance_days.punctuality', 'on_time')
                ->count();

            $late = $this->baseAttendanceDayQuery($organizationId, $branchRootId, $todayDate->toDateString())
                ->when($unitId !== null, fn (Builder $query): Builder => $this->applyAttendanceUnitScope($query, $unitId, $iso))
                ->whereDate('employee_attendance_days.work_date', '=', $iso, 'and')
                ->where('employee_attendance_days.punctuality', 'late')
                ->count();

            $visibleEmployeeIds = Employee::query()
                ->whereNull('employees.deleted_at', 'and', false)
                ->where(function (Builder $query) use ($organizationId, $branchRootId, $iso): void {
                    EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $iso);
                })
                ->when(
                    $unitId !== null,
                    function (Builder $query) use ($unitId, $iso): Builder {
                        return $query->whereHas('assignments', function (Builder $assignmentQuery) use ($unitId, $iso): void {
                            $assignmentQuery->where('employee_assignments.organizational_unit_id', $unitId)
                                ->whereNull('employee_assignments.deleted_at')
                                ->where(function (Builder $dateQuery) use ($iso): void {
                                    $dateQuery->whereNull('employee_assignments.end_date')
                                        ->orWhereDate('employee_assignments.end_date', '>=', $iso);
                                });
                        });
                    }
                )
                ->pluck('employees.id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $attendanceEmployeeIds = EmployeeAttendanceDay::query()
                ->where('organization_id', $organizationId)
                ->whereDate('work_date', '=', $iso, 'and')
                ->whereNull('deleted_at', 'and', false)
                ->whereIn('employee_id', $visibleEmployeeIds, 'and', false)
                ->pluck('employee_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $approvedLeaveEmployeeIds = EmployeeLeaveDay::query()
                ->where('organization_id', $organizationId)
                ->whereDate('leave_date', '=', $iso, 'and')
                ->whereIn('employee_id', $visibleEmployeeIds, 'and', false)
                ->whereHas('employeeLeave', function (Builder $query) use ($unitId): void {
                    $query->whereNull('employee_leaves.deleted_at')
                        ->where('employee_leaves.status', EmployeeHrRecordStatus::Approved)
                        ->when($unitId !== null, fn (Builder $q): Builder => $q->where('employee_leaves.organizational_unit_id', $unitId));
                })
                ->pluck('employee_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $absent = collect($visibleEmployeeIds)
                ->diff($attendanceEmployeeIds)
                ->diff($approvedLeaveEmployeeIds)
                ->count();

            $rows[] = [
                'date' => $cursor->format('M j'),
                'on_time' => $onTime,
                'late' => $late,
                'absent' => $absent,
            ];
        }

        return [
            'half' => $half === 'second_half' ? 'second_half' : 'first_half',
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     lates_today: list<array{id: int, display_name: string, id_number: string, unit_name: string, unit_code: string|null}>,
     *     absent_yesterday: list<array{id: int, display_name: string, id_number: string, unit_name: string, unit_code: string|null}>,
     *     on_leave_today: list<array{id: int, display_name: string, id_number: string, unit_name: string, unit_code: string|null}>,
     *     ot_yesterday: list<array{id: int, display_name: string, id_number: string, unit_name: string, unit_code: string|null}>
     * }
     */
    private function buildKpiEmployees(int $organizationId, int $branchRootId, string $today, array $filters): array
    {
        $todayDate = CarbonImmutable::parse($today);
        $yesterdayDate = $todayDate->subDay();
        $todayIso = $todayDate->toDateString();
        $yesterdayIso = $yesterdayDate->toDateString();
        $unitId = isset($filters['unit_id']) && $filters['unit_id'] !== null ? (int) $filters['unit_id'] : null;

        $latesTodayIds = $this->baseAttendanceDayQuery($organizationId, $branchRootId, $todayIso)
            ->when($unitId !== null, fn (Builder $query): Builder => $this->applyAttendanceUnitScope($query, $unitId, $todayIso))
            ->whereDate('employee_attendance_days.work_date', '=', $todayIso, 'and')
            ->where('employee_attendance_days.punctuality', 'late')
            ->pluck('employee_attendance_days.employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $onLeaveTodayIds = EmployeeLeaveDay::query()
            ->where('employee_leave_days.organization_id', $organizationId)
            ->whereDate('employee_leave_days.leave_date', '=', $todayIso, 'and')
            ->whereHas('employee', function (Builder $query) use ($organizationId, $branchRootId, $todayIso): void {
                $query->whereNull('employees.deleted_at');
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $todayIso);
            })
            ->whereHas('employeeLeave', function (Builder $query) use ($unitId): void {
                $query->whereNull('employee_leaves.deleted_at')
                    ->where('employee_leaves.status', EmployeeHrRecordStatus::Approved)
                    ->when($unitId !== null, fn (Builder $q): Builder => $q->where('employee_leaves.organizational_unit_id', $unitId));
            })
            ->pluck('employee_leave_days.employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $otYesterdayIds = EmployeeOvertime::query()
            ->where('employee_overtimes.organization_id', $organizationId)
            ->whereNull('employee_overtimes.deleted_at', 'and', false)
            ->whereDate('employee_overtimes.ot_date', '=', $yesterdayIso, 'and')
            ->where('employee_overtimes.status', EmployeeHrRecordStatus::Approved)
            ->whereHas('employee', function (Builder $query) use ($organizationId, $branchRootId, $todayIso): void {
                $query->whereNull('employees.deleted_at');
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $todayIso);
            })
            ->when($unitId !== null, fn (Builder $query): Builder => $query->where('employee_overtimes.organizational_unit_id', $unitId))
            ->pluck('employee_overtimes.employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $visibleEmployeeIds = Employee::query()
            ->whereNull('employees.deleted_at', 'and', false)
            ->where(function (Builder $query) use ($organizationId, $branchRootId, $yesterdayIso): void {
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $yesterdayIso);
            })
            ->when(
                $unitId !== null,
                function (Builder $query) use ($unitId, $yesterdayIso): Builder {
                    return $query->whereHas('assignments', function (Builder $assignmentQuery) use ($unitId, $yesterdayIso): void {
                        $assignmentQuery->where('employee_assignments.organizational_unit_id', $unitId)
                            ->whereNull('employee_assignments.deleted_at')
                            ->where(function (Builder $dateQuery) use ($yesterdayIso): void {
                                $dateQuery->whereNull('employee_assignments.end_date')
                                    ->orWhereDate('employee_assignments.end_date', '>=', $yesterdayIso);
                            });
                    });
                }
            )
            ->pluck('employees.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $attendanceYesterdayIds = EmployeeAttendanceDay::query()
            ->where('organization_id', $organizationId)
            ->whereDate('work_date', '=', $yesterdayIso, 'and')
            ->whereNull('deleted_at', 'and', false)
            ->whereIn('employee_id', $visibleEmployeeIds, 'and', false)
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $approvedLeaveYesterdayIds = EmployeeLeaveDay::query()
            ->where('organization_id', $organizationId)
            ->whereDate('leave_date', '=', $yesterdayIso, 'and')
            ->whereIn('employee_id', $visibleEmployeeIds, 'and', false)
            ->whereHas('employeeLeave', function (Builder $query) use ($unitId): void {
                $query->whereNull('employee_leaves.deleted_at')
                    ->where('employee_leaves.status', EmployeeHrRecordStatus::Approved)
                    ->when($unitId !== null, fn (Builder $q): Builder => $q->where('employee_leaves.organizational_unit_id', $unitId));
            })
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $absentYesterdayIds = collect($visibleEmployeeIds)
            ->diff($attendanceYesterdayIds)
            ->diff($approvedLeaveYesterdayIds)
            ->values()
            ->all();

        return [
            'lates_today' => $this->employeeListRowsByIds($latesTodayIds),
            'absent_yesterday' => $this->employeeListRowsByIds($absentYesterdayIds),
            'on_leave_today' => $this->employeeListRowsByIds($onLeaveTodayIds),
            'ot_yesterday' => $this->employeeListRowsByIds($otYesterdayIds),
        ];
    }

    /**
     * @param  list<int>  $employeeIds
     * @return list<array{id: int, display_name: string, id_number: string, unit_name: string, unit_code: string|null}>
     */
    private function employeeListRowsByIds(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('employees.id', $employeeIds, 'and', false)
            ->with([
                'currentEmployment',
                'assignments.organizationalUnit:id,name,code',
            ])
            ->get()
            ->sortBy(function (Employee $employee): string {
                return TeamHrEmployeeDisplay::fullName($employee);
            })
            ->values()
            ->map(static function (Employee $employee): array {
                $assignment = $employee->assignments
                    ->sortByDesc(fn ($row) => (bool) $row->is_primary)
                    ->first();
                $unit = $assignment?->organizationalUnit;

                return [
                    'id' => (int) $employee->id,
                    'display_name' => TeamHrEmployeeDisplay::fullName($employee),
                    'id_number' => (string) $employee->id_number,
                    'unit_name' => $unit?->name !== null ? (string) $unit->name : 'Unassigned',
                    'unit_code' => $unit?->code !== null && $unit->code !== ''
                        ? (string) $unit->code
                        : null,
                ];
            })
            ->all();
    }

    private function applyAttendanceUnitScope(Builder $query, int $unitId, string $today): Builder
    {
        return $query->where(function (Builder $w) use ($unitId, $today): void {
            $w->where('employee_attendance_days.organizational_unit_id', $unitId)
                ->orWhereHas('employee.assignments', function (Builder $a) use ($unitId, $today): void {
                    $a->where('employee_assignments.organizational_unit_id', $unitId)
                        ->whereNull('employee_assignments.deleted_at')
                        ->where(function (Builder $q2) use ($today): void {
                            $q2->whereNull('employee_assignments.end_date')
                                ->orWhereDate('employee_assignments.end_date', '>=', $today);
                        });
                });
        });
    }
}
