<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Http\Requests\IndexTeamHrEmployeeLeavesRequest;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Support\EmployeeBranchDirectoryFilter;
use App\Support\TeamHrEmployeeDirectoryExtras;
use App\Support\TeamHrEmployeeDisplay;
use App\Support\TeamHrLeaveRecordPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class TeamHrEmployeeLeavesPageService
{
    private const EMPLOYEE_FILTER_OPTION_LIMIT = 500;

    public function __construct(
        private BranchContextService $branchContextService,
        private ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
    ) {}

    /**
     * @return array{
     *     paginator: LengthAwarePaginator<int, array<string, mixed>>,
     *     employeeFilterOptions: list<array{id_number: string, display_name: string}>,
     *     filters: array<string, mixed>
     * }
     */
    public function buildPage(IndexTeamHrEmployeeLeavesRequest $request): array
    {
        $validated = $request->validated();
        $organization = $this->branchContextService->defaultOrganization();
        $workspace = $this->branchContextService->workspaceBranchContext($request);

        $page = (int) $validated['page'];
        $perPage = (int) $validated['per_page'];

        if ($organization === null || $workspace === null) {
            $filters = $request->inertiaTeamLeaveFilters();
            $filters['unit_id'] = null;

            return [
                'paginator' => new LengthAwarePaginator([], 0, $perPage, $page, [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]),
                'employeeFilterOptions' => [],
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

        $baseQuery = $this->baseEmployeeLeaveQuery($orgId, $branchRootId, $today);

        $queryForEmployeeOptions = clone $baseQuery;
        $this->applyLeaveFilters($queryForEmployeeOptions, $validated, applyEmployeeColumnFilter: false);
        $employeeFilterOptions = $this->buildEmployeeFilterOptions($queryForEmployeeOptions);

        $paginatedQuery = clone $baseQuery;
        $this->applyLeaveFilters($paginatedQuery, $validated, applyEmployeeColumnFilter: true);

        $sort = (string) ($validated['sort'] ?? 'dates');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        $paginator = $paginatedQuery
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'leavePolicy:id,code,name,organization_id',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
                'leaveDays',
            ])
            ->when(
                $sort === 'status',
                static fn (Builder $q): Builder => $q
                    ->orderBy('employee_leaves.status', $sortDirection)
                    ->orderBy('employee_leaves.start_date', 'desc')
                    ->orderByDesc('employee_leaves.id'),
                static fn (Builder $q): Builder => $q
                    ->when(
                        $sort === 'approve_date',
                        static fn (Builder $q2): Builder => $q2
                            ->orderBy('employee_leaves.decided_at', $sortDirection)
                            ->orderBy('employee_leaves.start_date', 'desc')
                            ->orderByDesc('employee_leaves.id'),
                        static fn (Builder $q2): Builder => $q2
                            ->orderBy('employee_leaves.start_date', $sortDirection)
                            ->orderByDesc('employee_leaves.id'),
                    ),
            )
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->getCollection()->transform(static fn (EmployeeLeave $leave): array => TeamHrLeaveRecordPresenter::toPageRow($leave));

        return [
            'paginator' => $paginator,
            'employeeFilterOptions' => $employeeFilterOptions,
            'filters' => [
                ...$request->inertiaTeamLeaveFilters(),
                'unit_id' => $validated['unit_id'] ?? null,
            ],
        ];
    }

    /**
     * @return Builder<EmployeeLeave>
     */
    private function baseEmployeeLeaveQuery(int $organizationId, int $branchRootId, string $today): Builder
    {
        return EmployeeLeave::query()
            ->where('employee_leaves.organization_id', $organizationId)
            ->whereNull('employee_leaves.deleted_at')
            ->whereHas('employee', function ($query) use ($organizationId, $branchRootId, $today): void {
                $query->whereNull('employees.deleted_at');
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $today);
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyLeaveFilters(Builder $query, array $filters, bool $applyEmployeeColumnFilter): void
    {
        $query->whereDate('employee_leaves.start_date', '<=', $filters['date_to'])
            ->whereDate('employee_leaves.end_date', '>=', $filters['date_from']);

        if (! empty($filters['unit_id'])) {
            $query->where('employee_leaves.organizational_unit_id', (int) $filters['unit_id']);
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $query->where(
                'employee_leaves.status',
                EmployeeHrRecordStatus::from($status === 'approved' ? 'approved' : 'rejected'),
            );
        }

        if (
            $applyEmployeeColumnFilter
            && isset($filters['employee_id_number'])
            && is_string($filters['employee_id_number'])
            && $filters['employee_id_number'] !== ''
        ) {
            $idNumber = (string) $filters['employee_id_number'];
            $query->whereHas('employee', static fn (Builder $q): Builder => $q->where('employees.id_number', $idNumber));
        }

        if (
            isset($filters['leave_type'])
            && is_string($filters['leave_type'])
            && $filters['leave_type'] !== ''
        ) {
            $code = (string) $filters['leave_type'];
            $query->whereHas('leavePolicy', static fn (Builder $q): Builder => $q->where('leave_policies.code', $code));
        }

        if (
            ! empty($filters['approve_from'])
            && ! empty($filters['approve_to'])
        ) {
            $query->whereNotNull('employee_leaves.decided_at')
                ->whereDate('employee_leaves.decided_at', '>=', $filters['approve_from'])
                ->whereDate('employee_leaves.decided_at', '<=', $filters['approve_to']);
        }

        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $w) use ($like): void {
                $w->whereHas('employee', function (Builder $e) use ($like): void {
                    $e->where('employees.id_number', 'like', $like)
                        ->orWhere('employees.first_name', 'like', $like)
                        ->orWhere('employees.last_name', 'like', $like)
                        ->orWhere('employees.middle_name', 'like', $like);
                })->orWhereHas('leavePolicy', function (Builder $p) use ($like): void {
                    $p->where('leave_policies.code', 'like', $like)
                        ->orWhere('leave_policies.name', 'like', $like);
                })->orWhere('employee_leaves.reason', 'like', $like);
            });
        }
    }

    /**
     * @return list<array{id_number: string, display_name: string}>
     */
    private function buildEmployeeFilterOptions(Builder $query): array
    {
        /** @var list<int> $ids */
        $ids = $query->clone()
            ->distinct()
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (count($ids) > self::EMPLOYEE_FILTER_OPTION_LIMIT) {
            $ids = array_slice($ids, 0, self::EMPLOYEE_FILTER_OPTION_LIMIT);
        }

        if ($ids === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', $ids)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(static fn (Employee $e): array => [
                'id_number' => (string) $e->id_number,
                'display_name' => TeamHrEmployeeDisplay::fullName($e),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function leavePolicyOptions(?Organization $organization): array
    {
        if ($organization === null) {
            return [];
        }

        return LeavePolicy::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(static fn (LeavePolicy $p): array => [
                'code' => (string) $p->code,
                'name' => (string) $p->name,
            ])
            ->values()
            ->all();
    }
}
