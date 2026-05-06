<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPositionsJobHistoryRequest;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Services\BranchContextService;
use App\Support\EmployeeBranchDirectoryFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PositionsJobHistoryController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(IndexPositionsJobHistoryRequest $request): Response
    {
        $validated = $request->validated();
        $organization = $this->branchContextService->defaultOrganization();
        $parsed = $this->parseInputs($validated);

        if ($organization === null) {
            return Inertia::render('Positions/JobHistory', [
                'organization' => null,
                'branchScope' => null,
                'jobHistory' => $this->emptyPaginator($request, $parsed['perPage'], $parsed['page']),
                'filters' => $parsed['filters'],
            ]);
        }

        $today = Carbon::today();
        $todayString = $today->toDateString();
        $orgId = (int) $organization->id;
        $branchRootId = $this->resolveBranchRootIdForEmployeeDirectory($request, $organization);

        $visibleEmployees = Employee::query()
            ->select('employees.id')
            ->whereNull('employees.deleted_at', 'and', false);
        EmployeeBranchDirectoryFilter::apply(
            $visibleEmployees,
            $orgId,
            $branchRootId,
            $todayString,
            true,
        );

        $query = $parsed['historyType'] === 'unit_assignments'
            ? $this->buildUnitAssignmentsQuery($parsed, $visibleEmployees, $orgId, $todayString, $validated)
            : $this->buildPositionsQuery($parsed, $visibleEmployees, $orgId, $todayString, $validated);

        $paginator = $query->paginate($parsed['perPage'], ['*'], 'page', $parsed['page'])->withQueryString();
        if ($parsed['historyType'] === 'unit_assignments') {
            $paginator->load([
                'employee' => function ($q) use ($today, $orgId): void {
                    $q->select([
                        'employees.id',
                        'employees.first_name',
                        'employees.middle_name',
                        'employees.last_name',
                        'employees.suffix',
                        'employees.id_number',
                    ])
                        ->with([
                            'affiliations' => function ($aq) use ($today, $orgId): void {
                                $aq->select([
                                    'employee_affiliations.id',
                                    'employee_affiliations.employee_id',
                                    'employee_affiliations.organization_id',
                                    'employee_affiliations.root_unit_id',
                                    'employee_affiliations.end_date',
                                ])
                                    ->where('organization_id', $orgId)
                                    ->whereNull('root_unit_id')
                                    ->whereNull('deleted_at')
                                    ->where(function ($q2) use ($today): void {
                                        $q2->whereNull('end_date')
                                            ->orWhereDate('end_date', '>=', $today);
                                    });
                            },
                        ]);
                },
                'organizationalUnit:id,code,name,unit_type_id',
                'organizationalUnit.unitType:id,name',
                'employmentPeriod:id,employment_status',
            ]);
            $mappedPaginator = $paginator->through(fn (EmployeeAssignment $row): array => $this->mapUnitAssignmentRow($row, $today));
        } else {
            $paginator->load([
                'employee' => function ($q) use ($today, $orgId): void {
                    $q->select([
                        'employees.id',
                        'employees.first_name',
                        'employees.middle_name',
                        'employees.last_name',
                        'employees.suffix',
                        'employees.id_number',
                    ])
                        ->with([
                            'affiliations' => function ($aq) use ($today, $orgId): void {
                                $aq->select([
                                    'employee_affiliations.id',
                                    'employee_affiliations.employee_id',
                                    'employee_affiliations.organization_id',
                                    'employee_affiliations.root_unit_id',
                                    'employee_affiliations.end_date',
                                ])
                                    ->where('organization_id', $orgId)
                                    ->whereNull('root_unit_id')
                                    ->whereNull('deleted_at')
                                    ->where(function ($q2) use ($today): void {
                                        $q2->whereNull('end_date')
                                            ->orWhereDate('end_date', '>=', $today);
                                    });
                            },
                        ]);
                },
                'position:id,code,title',
                'employmentPeriod:id,employment_status',
            ]);
            $mappedPaginator = $paginator->through(fn (EmployeePosition $row): array => $this->mapPositionRow($row, $today));
        }

        return Inertia::render('Positions/JobHistory', [
            'organization' => [
                'id' => $orgId,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
            ],
            'branchScope' => $this->resolveBranchScopePayload($branchRootId, $organization),
            'jobHistory' => $mappedPaginator,
            'filters' => $parsed['filters'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     perPage: int,
     *     page: int,
     *     search: string,
     *     historyType: string,
     *     employmentStatus: string|null,
     *     startFrom: string|null,
     *     startTo: string|null,
     *     endFrom: string|null,
     *     endTo: string|null,
     *     filters: array<string, mixed>,
     * }
     */
    private function parseInputs(array $validated): array
    {
        $perPage = (int) $validated['per_page'];
        $page = (int) $validated['page'];
        $search = isset($validated['search']) && $validated['search'] !== ''
            ? (string) $validated['search']
            : '';
        $historyType = (string) $validated['history_type'];
        $employmentStatus = isset($validated['employment_status']) && $validated['employment_status'] !== null
            ? (string) $validated['employment_status']
            : null;
        $startFrom = isset($validated['start_from']) && $validated['start_from'] !== null
            ? (string) $validated['start_from']
            : null;
        $startTo = isset($validated['start_to']) && $validated['start_to'] !== null
            ? (string) $validated['start_to']
            : null;
        $endFrom = isset($validated['end_from']) && $validated['end_from'] !== null
            ? (string) $validated['end_from']
            : null;
        $endTo = isset($validated['end_to']) && $validated['end_to'] !== null
            ? (string) $validated['end_to']
            : null;

        return [
            'perPage' => $perPage,
            'page' => $page,
            'search' => $search,
            'historyType' => $historyType,
            'employmentStatus' => $employmentStatus,
            'startFrom' => $startFrom,
            'startTo' => $startTo,
            'endFrom' => $endFrom,
            'endTo' => $endTo,
            'filters' => [
                'search' => $search,
                'history_type' => $historyType,
                'employment_status' => $employmentStatus,
                'start_from' => $startFrom,
                'start_to' => $startTo,
                'end_from' => $endFrom,
                'end_to' => $endTo,
                'sort' => (string) $validated['sort'],
                'direction' => (string) $validated['direction'],
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     employee: array{id: int, display_name: string, id_number: string, is_org_wide: bool},
     *     position: array{id: int, code: string, title: string},
     *     start_date: string,
     *     end_date: string|null,
     *     total_days: int,
     *     job_status: 'current'|'ended',
     *     employment_status: string,
     * }
     */
    private function mapPositionRow(EmployeePosition $row, Carbon $today): array
    {
        $start = Carbon::parse($row->start_date)->startOfDay();
        $end = $row->end_date !== null
            ? Carbon::parse($row->end_date)->startOfDay()
            : $today->copy()->startOfDay();
        $daySpan = (int) $start->diffInDays($end, false);
        $totalDays = $daySpan >= 0 ? $daySpan + 1 : 1;

        $employee = $row->employee;
        if ($employee === null) {
            throw new \RuntimeException('employee position row '.$row->getKey().' missing employee.');
        }

        $position = $row->position;
        if ($position === null) {
            throw new \RuntimeException('employee position row '.$row->getKey().' missing position.');
        }

        $employment = $row->employmentPeriod;
        if ($employment === null) {
            throw new \RuntimeException('employee position row '.$row->getKey().' missing employment period.');
        }

        return [
            'id' => (int) $row->id,
            'employee' => [
                'id' => (int) $employee->id,
                'display_name' => $this->formatEmployeeDisplayName($employee),
                'id_number' => (string) $employee->id_number,
                'is_org_wide' => $employee->relationLoaded('affiliations')
                    ? $employee->affiliations->isNotEmpty()
                    : false,
            ],
            'position' => [
                'id' => (int) $position->id,
                'code' => (string) $position->code,
                'title' => (string) $position->title,
            ],
            'organizational_unit' => null,
            'start_date' => Carbon::parse($row->start_date)->toDateString(),
            'end_date' => $row->end_date !== null ? Carbon::parse($row->end_date)->toDateString() : null,
            'total_days' => $totalDays,
            'job_status' => $row->end_date === null ? 'current' : 'ended',
            'employment_status' => (string) $employment->employment_status,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     employee: array{id: int, display_name: string, id_number: string, is_org_wide: bool},
     *     position: null,
     *     organizational_unit: array{id: int, code: string, name: string, unit_type: string|null}|null,
     *     start_date: string,
     *     end_date: string|null,
     *     total_days: int,
     *     job_status: 'current'|'ended',
     *     employment_status: string,
     * }
     */
    private function mapUnitAssignmentRow(EmployeeAssignment $row, Carbon $today): array
    {
        $start = Carbon::parse($row->start_date)->startOfDay();
        $end = $row->end_date !== null
            ? Carbon::parse($row->end_date)->startOfDay()
            : $today->copy()->startOfDay();
        $daySpan = (int) $start->diffInDays($end, false);
        $totalDays = $daySpan >= 0 ? $daySpan + 1 : 1;

        $employee = $row->employee;
        if ($employee === null) {
            throw new \RuntimeException('employee assignment row '.$row->getKey().' missing employee.');
        }

        $employment = $row->employmentPeriod;
        if ($employment === null) {
            throw new \RuntimeException('employee assignment row '.$row->getKey().' missing employment period.');
        }

        $unit = $row->organizationalUnit;

        return [
            'id' => (int) $row->id,
            'employee' => [
                'id' => (int) $employee->id,
                'display_name' => $this->formatEmployeeDisplayName($employee),
                'id_number' => (string) $employee->id_number,
                'is_org_wide' => $employee->relationLoaded('affiliations')
                    ? $employee->affiliations->isNotEmpty()
                    : false,
            ],
            'position' => null,
            'organizational_unit' => $unit !== null ? [
                'id' => (int) $unit->id,
                'code' => (string) $unit->code,
                'name' => (string) $unit->name,
                'unit_type' => $unit->unitType?->name,
            ] : null,
            'start_date' => Carbon::parse($row->start_date)->toDateString(),
            'end_date' => $row->end_date !== null ? Carbon::parse($row->end_date)->toDateString() : null,
            'total_days' => $totalDays,
            'job_status' => $row->end_date === null ? 'current' : 'ended',
            'employment_status' => (string) $employment->employment_status,
        ];
    }

    /**
     * @param  array{
     *     search: string,
     *     historyType: string,
     *     employmentStatus: string|null,
     *     startFrom: string|null,
     *     startTo: string|null,
     *     endFrom: string|null,
     *     endTo: string|null,
     * }  $parsed
     * @param  array<string, mixed>  $validated
     */
    private function buildPositionsQuery(array $parsed, mixed $visibleEmployees, int $orgId, string $todayString, array $validated): Builder
    {
        $query = EmployeePosition::query()
            ->select('employee_positions.*')
            ->join('employees', 'employees.id', '=', 'employee_positions.employee_id', 'inner', false)
            ->join('employee_employments', 'employee_employments.id', '=', 'employee_positions.employee_employment_id', 'inner', false)
            ->join('positions', 'positions.id', '=', 'employee_positions.position_id', 'inner', false)
            ->where('positions.organization_id', $orgId)
            ->whereNull('employee_positions.deleted_at', 'and', false)
            ->whereNull('employees.deleted_at', 'and', false)
            ->whereNull('employee_employments.deleted_at', 'and', false)
            ->whereIn('employee_positions.employee_id', $visibleEmployees, 'and', false);

        $this->applyCommonHistoryFilters(
            $query,
            $parsed,
            $todayString,
            function ($q, string $term): void {
                $q->where(function ($q2) use ($term): void {
                    $q2->where('employees.id_number', 'like', $term)
                        ->orWhere('employees.first_name', 'like', $term)
                        ->orWhere('employees.last_name', 'like', $term)
                        ->orWhere('employees.middle_name', 'like', $term)
                        ->orWhere('employees.suffix', 'like', $term)
                        ->orWhere('positions.code', 'like', $term)
                        ->orWhere('positions.title', 'like', $term);
                });
            },
            'employee_positions.start_date',
            'employee_positions.end_date',
        );
        $this->applySorting(
            $query,
            (string) $validated['sort'],
            (string) $validated['direction'],
            $todayString,
            'employee_positions.start_date',
            'employee_positions.end_date',
        );

        $query->orderBy('employees.last_name', 'asc')
            ->orderBy('employees.first_name', 'asc')
            ->orderBy('employee_positions.id', 'asc');

        return $query;
    }

    /**
     * @param  array{
     *     search: string,
     *     historyType: string,
     *     employmentStatus: string|null,
     *     startFrom: string|null,
     *     startTo: string|null,
     *     endFrom: string|null,
     *     endTo: string|null,
     * }  $parsed
     * @param  array<string, mixed>  $validated
     */
    private function buildUnitAssignmentsQuery(array $parsed, mixed $visibleEmployees, int $orgId, string $todayString, array $validated): Builder
    {
        $query = EmployeeAssignment::query()
            ->select('employee_assignments.*')
            ->join('employees', 'employees.id', '=', 'employee_assignments.employee_id', 'inner', false)
            ->join('employee_employments', 'employee_employments.id', '=', 'employee_assignments.employee_employment_id', 'inner', false)
            ->leftJoin('organizational_units', 'organizational_units.id', '=', 'employee_assignments.organizational_unit_id')
            ->whereNull('employee_assignments.deleted_at', 'and', false)
            ->whereNull('employees.deleted_at', 'and', false)
            ->whereNull('employee_employments.deleted_at', 'and', false)
            ->where(function ($q) use ($orgId): void {
                $q->where('employee_assignments.organization_id', $orgId)
                    ->orWhere('organizational_units.organization_id', $orgId);
            })
            ->whereIn('employee_assignments.employee_id', $visibleEmployees, 'and', false);

        $this->applyCommonHistoryFilters(
            $query,
            $parsed,
            $todayString,
            function ($q, string $term): void {
                $q->where(function ($q2) use ($term): void {
                    $q2->where('employees.id_number', 'like', $term)
                        ->orWhere('employees.first_name', 'like', $term)
                        ->orWhere('employees.last_name', 'like', $term)
                        ->orWhere('employees.middle_name', 'like', $term)
                        ->orWhere('employees.suffix', 'like', $term)
                        ->orWhere('organizational_units.code', 'like', $term)
                        ->orWhere('organizational_units.name', 'like', $term);
                });
            },
            'employee_assignments.start_date',
            'employee_assignments.end_date',
        );
        $this->applySorting(
            $query,
            (string) $validated['sort'],
            (string) $validated['direction'],
            $todayString,
            'employee_assignments.start_date',
            'employee_assignments.end_date',
        );

        $query->orderBy('employees.last_name', 'asc')
            ->orderBy('employees.first_name', 'asc')
            ->orderBy('employee_assignments.id', 'asc');

        return $query;
    }

    /**
     * @param  array{
     *     search: string,
     *     historyType: string,
     *     employmentStatus: string|null,
     *     startFrom: string|null,
     *     startTo: string|null,
     *     endFrom: string|null,
     *     endTo: string|null,
     * }  $parsed
     */
    private function applyCommonHistoryFilters(
        Builder $query,
        array $parsed,
        string $todayString,
        \Closure $searchCallback,
        string $startColumn,
        string $endColumn,
    ): void {
        if ($parsed['employmentStatus'] !== null) {
            $query->where('employee_employments.employment_status', $parsed['employmentStatus']);
        }
        if ($parsed['search'] !== '') {
            $searchCallback($query, '%'.$parsed['search'].'%');
        }
        if ($parsed['startFrom'] !== null && $parsed['startTo'] !== null) {
            $query->whereDate($startColumn, '>=', $parsed['startFrom'], 'and')
                ->whereDate($startColumn, '<=', $parsed['startTo'], 'and');
        }
        if ($parsed['endFrom'] !== null && $parsed['endTo'] !== null) {
            $query->whereDate($startColumn, '<=', $parsed['endTo'], 'and')
                ->whereRaw(
                    'COALESCE('.$endColumn.', ?) >= ?',
                    [$todayString, $parsed['endFrom']],
                    'and',
                );
        }
    }

    private function applySorting(
        Builder $query,
        string $sort,
        string $directionInput,
        string $todayString,
        string $startColumn,
        string $endColumn,
    ): void {
        $direction = $directionInput === 'asc' ? 'asc' : 'desc';
        if ($sort === 'total_days') {
            $driver = (string) config(
                'database.connections.'.config('database.default').'.driver',
                'mysql',
            );
            if ($driver === 'sqlite') {
                $query->orderByRaw(
                    '(julianday(COALESCE('.$endColumn.', ?)) - julianday('.$startColumn.')) '.$direction,
                    [$todayString],
                );
            } else {
                $query->orderByRaw(
                    'DATEDIFF(COALESCE('.$endColumn.', ?), '.$startColumn.') '.$direction,
                    [$todayString],
                );
            }

            return;
        }

        $sortColumn = match ($sort) {
            'end_date' => $endColumn,
            'last_name' => 'employees.last_name',
            'id_number' => 'employees.id_number',
            'employment_status' => 'employee_employments.employment_status',
            default => $startColumn,
        };
        $query->orderBy($sortColumn, $direction);
    }

    private function formatEmployeeDisplayName(Employee $employee): string
    {
        $parts = array_values(array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->suffix,
        ], fn (?string $part): bool => filled($part)));

        if ($parts === []) {
            return 'Employee #'.$employee->id;
        }

        return implode(' ', $parts);
    }

    private function emptyPaginator(Request $request, int $perPage, int $page): LengthAwarePaginator
    {
        $emptyPaginator = new LengthAwarePaginator(
            [],
            0,
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'page'],
        );
        $emptyPaginator->withQueryString();

        return $emptyPaginator;
    }

    private function resolveBranchRootIdForEmployeeDirectory(Request $request, Organization $organization): ?int
    {
        $user = $request->user();
        if ($user === null || ! $user->mustSelectBranch()) {
            return null;
        }

        $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);
        if ($branchId <= 0 || ! $this->branchContextService->isValidSessionBranchId($branchId)) {
            return null;
        }

        $root = $this->branchContextService->findSelectableBranchRoot($branchId, $organization);
        if ($root === null || (int) $root->organization_id !== (int) $organization->id) {
            return null;
        }

        return $branchId;
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    private function resolveBranchScopePayload(?int $branchRootId, Organization $organization): ?array
    {
        if ($branchRootId === null) {
            return null;
        }

        $scopeUnit = OrganizationalUnit::query()
            ->whereKey($branchRootId)
            ->where('organization_id', $organization->id)
            ->first(['id', 'code', 'name']);

        if ($scopeUnit === null) {
            return null;
        }

        return [
            'id' => (int) $scopeUnit->id,
            'code' => (string) $scopeUnit->code,
            'name' => (string) $scopeUnit->name,
        ];
    }
}
