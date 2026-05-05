<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexEmployeeEmploymentHistoryRequest;
use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Services\BranchContextService;
use App\Support\EmployeeBranchDirectoryFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employment history rows for employees who pass directory visibility for organization/branch scope.
 *
 * All {@see EmployeeEmployment} records for each visible employee are listed, and visibility
 * includes historical (ended) org/branch links so separated employees are still discoverable.
 */
class EmployeesEmploymentHistoryController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(IndexEmployeeEmploymentHistoryRequest $request): Response
    {
        $validated = $request->validated();
        $organization = $this->branchContextService->defaultOrganization();
        $parsed = $this->parseInputs($validated);

        if ($organization === null) {
            return Inertia::render(
                'Employees/EmploymentHistory',
                $this->buildEmptyProps($request, $parsed),
            );
        }

        return Inertia::render(
            'Employees/EmploymentHistory',
            $this->buildProps($request, $organization, $validated, $parsed),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     perPage: int,
     *     page: int,
     *     search: string,
     *     employmentStatus: string|null,
     *     employeeId: int|null,
     *     hireFrom: string|null,
     *     hireTo: string|null,
     *     separationFrom: string|null,
     *     separationTo: string|null,
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

        $employmentStatus = isset($validated['employment_status']) && $validated['employment_status'] !== null
            ? (string) $validated['employment_status']
            : null;

        $employeeId = isset($validated['employee_id']) ? (int) $validated['employee_id'] : null;

        $hireFrom = isset($validated['hire_from']) && $validated['hire_from'] !== null
            ? (string) $validated['hire_from']
            : null;
        $hireTo = isset($validated['hire_to']) && $validated['hire_to'] !== null
            ? (string) $validated['hire_to']
            : null;
        $separationFrom = isset($validated['separation_from']) && $validated['separation_from'] !== null
            ? (string) $validated['separation_from']
            : null;
        $separationTo = isset($validated['separation_to']) && $validated['separation_to'] !== null
            ? (string) $validated['separation_to']
            : null;

        return [
            'perPage' => $perPage,
            'page' => $page,
            'search' => $search,
            'employmentStatus' => $employmentStatus,
            'employeeId' => $employeeId,
            'hireFrom' => $hireFrom,
            'hireTo' => $hireTo,
            'separationFrom' => $separationFrom,
            'separationTo' => $separationTo,
            'filters' => [
                'search' => $search,
                'employment_status' => $employmentStatus,
                'employee_id' => $employeeId,
                'hire_from' => $hireFrom,
                'hire_to' => $hireTo,
                'separation_from' => $separationFrom,
                'separation_to' => $separationTo,
                'sort' => (string) $validated['sort'],
                'direction' => (string) $validated['direction'],
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildEmptyProps(Request $request, array $parsed): array
    {
        $emptyPaginator = new LengthAwarePaginator(
            [],
            0,
            $parsed['perPage'],
            $parsed['page'],
            ['path' => $request->url(), 'pageName' => 'page'],
        );
        $emptyPaginator->withQueryString();

        return [
            'employments' => $emptyPaginator,
            'filters' => $parsed['filters'],
            'organization' => null,
            'branchScope' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildProps(
        Request $request,
        Organization $organization,
        array $validated,
        array $parsed,
    ): array {
        $today = Carbon::today();
        $todayString = $today->toDateString();
        $orgId = (int) $organization->id;
        $branchRootId = $this->resolveBranchRootIdForEmployeeDirectory($request, $organization);

        $visibleEmployees = Employee::query()
            ->select('employees.id')
            ->whereNull('employees.deleted_at');
        EmployeeBranchDirectoryFilter::apply(
            $visibleEmployees,
            $orgId,
            $branchRootId,
            $todayString,
            true,
        );

        $query = EmployeeEmployment::query()
            ->select('employee_employments.*')
            ->join('employees', 'employees.id', '=', 'employee_employments.employee_id')
            ->whereNull('employee_employments.deleted_at')
            ->whereNull('employees.deleted_at')
            ->whereIn('employee_employments.employee_id', $visibleEmployees);

        if ($parsed['employmentStatus'] !== null) {
            $query->where(
                'employee_employments.employment_status',
                $parsed['employmentStatus'],
            );
        }

        if ($parsed['employeeId'] !== null) {
            $query->where('employee_employments.employee_id', $parsed['employeeId']);
        }

        if ($parsed['search'] !== '') {
            $term = '%'.$parsed['search'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('employees.id_number', 'like', $term)
                    ->orWhere('employees.first_name', 'like', $term)
                    ->orWhere('employees.last_name', 'like', $term)
                    ->orWhere('employees.middle_name', 'like', $term)
                    ->orWhere('employees.suffix', 'like', $term);
            });
        }

        if ($parsed['hireFrom'] !== null && $parsed['hireTo'] !== null) {
            $query->whereDate('employee_employments.hire_date', '>=', $parsed['hireFrom'])
                ->whereDate('employee_employments.hire_date', '<=', $parsed['hireTo']);
        }

        if ($parsed['separationFrom'] !== null && $parsed['separationTo'] !== null) {
            $query->whereDate('employee_employments.hire_date', '<=', $parsed['separationTo'])
                ->whereRaw(
                    'COALESCE(employee_employments.separation_date, ?) >= ?',
                    [$todayString, $parsed['separationFrom']],
                );
        }

        $direction = $validated['direction'] === 'asc' ? 'asc' : 'desc';
        if ($validated['sort'] === 'tenure') {
            $driver = $query->getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                $query->orderByRaw(
                    '(julianday(COALESCE(employee_employments.separation_date, ?)) - julianday(employee_employments.hire_date)) '.$direction,
                    [$todayString],
                );
            } else {
                $query->orderByRaw(
                    'DATEDIFF(COALESCE(employee_employments.separation_date, ?), employee_employments.hire_date) '.$direction,
                    [$todayString],
                );
            }
        } else {
            $sortColumn = match ($validated['sort']) {
                'separation_date' => 'employee_employments.separation_date',
                'last_name' => 'employees.last_name',
                'id_number' => 'employees.id_number',
                'employment_status' => 'employee_employments.employment_status',
                default => 'employee_employments.hire_date',
            };

            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('employee_employments.id', 'asc');

        $paginator = $query->paginate($parsed['perPage'], ['*'], 'page', $parsed['page'])->withQueryString();

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
                    ->with(['user:id,employee_id,avatar_path'])
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
        ]);

        $mappedPaginator = $paginator->through(
            fn (EmployeeEmployment $employment): array => $this->mapEmploymentRow($employment, $today),
        );

        return [
            'employments' => $mappedPaginator,
            'filters' => $parsed['filters'],
            'organization' => [
                'id' => $orgId,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
            ],
            'branchScope' => $this->resolveBranchScopePayload($branchRootId, $organization),
        ];
    }

    /**
     * Tenure span is inclusive calendar days from hire_date through separation_date, or today's date when still employed.
     */
    private function mapEmploymentRow(EmployeeEmployment $employment, Carbon $today): array
    {
        $hire = Carbon::parse($employment->hire_date)->startOfDay();
        $separation = $employment->separation_date !== null
            ? Carbon::parse($employment->separation_date)->startOfDay()
            : null;
        $end = $separation ?? $today->copy()->startOfDay();

        $daySpan = (int) $hire->diffInDays($end, false);
        $tenureDays = $daySpan >= 0 ? $daySpan + 1 : 1;

        $employee = $employment->employee;
        if ($employee === null) {
            throw new \RuntimeException('employment row '.$employment->getKey().' missing employee.');
        }

        return [
            'id' => (int) $employment->id,
            'hire_date' => Carbon::parse($employment->hire_date)->toDateString(),
            'separation_date' => $employment->separation_date !== null
                ? Carbon::parse($employment->separation_date)->toDateString()
                : null,
            'employment_status' => (string) $employment->employment_status,
            'tenure_days' => $tenureDays,
            'employee' => [
                'id' => (int) $employee->id,
                'display_name' => $this->formatEmployeeDisplayName($employee),
                'id_number' => (string) $employee->id_number,
                'avatar_url' => $this->resolveAvatarUrl($employee),
                'is_org_wide' => $employee->relationLoaded('affiliations')
                    ? $employee->affiliations->isNotEmpty()
                    : false,
            ],
        ];
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

    private function resolveAvatarUrl(Employee $employee): ?string
    {
        $user = $employee->user;
        if ($user === null) {
            return null;
        }

        $avatarPath = $user->avatar_path;
        if ($avatarPath === null || $avatarPath === '') {
            return null;
        }

        return asset('storage/'.$avatarPath);
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
