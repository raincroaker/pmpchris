<?php

namespace App\Services;

use App\Enums\AttendanceRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Http\Requests\IndexTeamAttendanceRequest;
use App\Models\EmployeeAttendanceDay;
use App\Support\EmployeeBranchDirectoryFilter;
use App\Support\TeamAttendanceRecordPresenter;
use App\Support\TeamHrEmployeeDirectoryExtras;
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
     *     filters: array<string, mixed>
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

        return [
            'paginator' => $paginator,
            'filters' => $filters,
        ];
    }

    /**
     * @return Builder<EmployeeAttendanceDay>
     */
    private function baseAttendanceDayQuery(int $organizationId, int $branchRootId, string $today): Builder
    {
        return EmployeeAttendanceDay::query()
            ->where('employee_attendance_days.organization_id', $organizationId)
            ->whereNull('employee_attendance_days.deleted_at')
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
}
