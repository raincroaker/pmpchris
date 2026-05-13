<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexEmployeesRequest;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Services\BranchContextService;
use App\Services\EmploymentHireAdjustmentBoundary;
use App\Services\HrIndexUnitFilterCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class EmployeesIndexController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HrIndexUnitFilterCatalog $hrIndexUnitFilterCatalog,
    ) {}

    public function __invoke(IndexEmployeesRequest $request): Response
    {
        $validated = $request->validated();
        $organization = $this->branchContextService->defaultOrganization();
        $parsed = $this->parseIndexInputs($validated, $organization);
        if ($organization === null) {
            return Inertia::render('Employees/Index', $this->buildEmptyIndexProps($request, $parsed));
        }

        return Inertia::render('Employees/Index', $this->buildIndexProps(
            $request,
            $organization,
            $validated,
            $parsed,
        ));
    }

    /**
     * @param array{
     *     per_page: mixed,
     *     page: mixed,
     *     search?: mixed,
     *     sort: mixed,
     *     direction: mixed,
     *     position_id?: mixed,
     *     unit_id?: mixed,
     *     org_scope?: mixed,
     * } $validated
     * @return array{
     *     perPage: int,
     *     page: int,
     *     search: string,
     *     positionId: int|null,
     *     unitId: int|'unassigned'|null,
     *     orgScope: 'org_wide'|'branch_scoped'|null,
     *     filters: array{
     *         search: string,
     *         sort: string,
     *         direction: string,
     *         per_page: int,
     *         position_id: int|null,
     *         unit_id: int|'unassigned'|null,
     *         org_scope: 'org_wide'|'branch_scoped'|null
     *     }
     * }
     */
    private function parseIndexInputs(array $validated, ?Organization $organization): array
    {
        $perPage = (int) $validated['per_page'];
        $page = (int) $validated['page'];
        $search = isset($validated['search']) && $validated['search'] !== ''
            ? (string) $validated['search']
            : '';

        $positionId = isset($validated['position_id']) ? (int) $validated['position_id'] : null;
        $unitIdRaw = $validated['unit_id'] ?? null;
        $unitId = $unitIdRaw === 'unassigned'
            ? 'unassigned'
            : (isset($validated['unit_id']) ? (int) $validated['unit_id'] : null);
        $orgScope = isset($validated['org_scope']) && in_array($validated['org_scope'], ['org_wide', 'branch_scoped'], true)
            ? (string) $validated['org_scope']
            : null;
        if ($organization !== null && $positionId !== null) {
            $positionOk = Position::query()
                ->whereKey($positionId)
                ->where('organization_id', $organization->id)
                ->exists();
            if (! $positionOk) {
                $positionId = null;
            }
        } elseif ($organization === null) {
            $positionId = null;
        }

        $hireFrom = isset($validated['hire_from']) && $validated['hire_from'] !== null
            ? (string) $validated['hire_from']
            : null;
        $hireTo = isset($validated['hire_to']) && $validated['hire_to'] !== null
            ? (string) $validated['hire_to']
            : null;

        return [
            'perPage' => $perPage,
            'page' => $page,
            'search' => $search,
            'positionId' => $positionId,
            'unitId' => $unitId,
            'orgScope' => $orgScope,
            'hireFrom' => $hireFrom,
            'hireTo' => $hireTo,
            'filters' => [
                'search' => $search,
                'sort' => (string) $validated['sort'],
                'direction' => (string) $validated['direction'],
                'per_page' => $perPage,
                'position_id' => $positionId,
                'unit_id' => $unitId,
                'org_scope' => $orgScope,
                'hire_from' => $hireFrom,
                'hire_to' => $hireTo,
            ],
        ];
    }

    /**
     * @param array{
     *     perPage: int,
     *     page: int,
     *     filters: array{
     *         search: string,
     *         sort: string,
     *         direction: string,
     *         per_page: int,
     *         position_id: int|null,
     *         unit_id: int|null,
     *         org_scope: 'org_wide'|'branch_scoped'|null
     *     }
     * } $parsed
     * @return array{
     *     employees: LengthAwarePaginator<int, mixed>,
     *     filters: array{
     *         search: string,
     *         sort: string,
     *         direction: string,
     *         per_page: int,
     *         position_id: int|null,
     *         unit_id: int|null,
     *         org_scope: 'org_wide'|'branch_scoped'|null
     *     },
     *     positionFilterOptions: list<array<array-key, mixed>>,
     *     unitFilterOptions: list<array<array-key, mixed>>,
     *     organization: null,
     *     branchScope: null
     * }
     */
    private function buildEmptyIndexProps(Request $request, array $parsed): array
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
            'employees' => $emptyPaginator,
            'filters' => $parsed['filters'],
            'positionFilterOptions' => [],
            'unitFilterOptions' => [],
            'organization' => null,
            'branchScope' => null,
        ];
    }

    /**
     * @param array{
     *     sort: mixed,
     *     direction: mixed,
     * } $validated
     * @param array{
     *     perPage: int,
     *     page: int,
     *     search: string,
     *     positionId: int|null,
     *     unitId: int|'unassigned'|null,
     *     filters: array{
     *         search: string,
     *         sort: string,
     *         direction: string,
     *         per_page: int,
     *         position_id: int|null,
     *         unit_id: int|'unassigned'|null,
     *         org_scope: 'org_wide'|'branch_scoped'|null
     *     }
     * } $parsed
     * @return array{
     *     employees: LengthAwarePaginator<int, mixed>,
     *     filters: array{
     *         search: string,
     *         sort: string,
     *         direction: string,
     *         per_page: int,
     *         position_id: int|null,
     *         unit_id: int|null,
     *         org_scope: 'org_wide'|'branch_scoped'|null
     *     },
     *     positionFilterOptions: list<array{id: int, code: string, title: string}>,
     *     unitFilterOptions: list<array{id: int, code: string, name: string, unit_type: string}>,
     *     organization: array{id: int, code: string, name: string},
     *     branchScope: array{id: int, code: string, name: string}|null
     * }
     */
    private function buildIndexProps(
        Request $request,
        Organization $organization,
        array $validated,
        array $parsed,
    ): array {
        $filters = $parsed['filters'];
        $today = now()->toDateString();
        $orgId = (int) $organization->id;
        $branchRootId = $this->resolveBranchRootIdForEmployeeIndex($request, $organization);
        $allowedUnitIds = $this->hrIndexUnitFilterCatalog->allowedUnitIds($orgId, $branchRootId);
        $unitId = $parsed['unitId'];
        if (is_int($unitId) && ! in_array($unitId, $allowedUnitIds, true)) {
            $unitId = null;
            $filters['unit_id'] = null;
        }

        $query = Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.middle_name',
                'employees.last_name',
                'employees.suffix',
                'employees.id_number',
            ])
            ->whereNull('employees.deleted_at')
            ->whereHas('currentEmployment', function ($q): void {
                $q->whereNull('employee_employments.deleted_at')
                    ->where('employee_employments.employment_status', EmployeeEmployment::STATUS_ACTIVE)
                    ->whereNull('employee_employments.separation_date');
            });

        if ($branchRootId !== null) {
            $query->whereExists(function ($q) use ($orgId, $branchRootId, $today): void {
                $q->selectRaw('1')
                    ->from('employee_affiliations')
                    ->whereColumn('employee_affiliations.employee_id', 'employees.id')
                    ->where('employee_affiliations.organization_id', $orgId)
                    ->where(function ($q2) use ($branchRootId): void {
                        $q2->where('employee_affiliations.root_unit_id', $branchRootId)
                            ->orWhereNull('employee_affiliations.root_unit_id');
                    })
                    ->whereNull('employee_affiliations.deleted_at')
                    ->where(function ($q3) use ($today): void {
                        $q3->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        } else {
            $query->where(function ($outer) use ($orgId, $today): void {
                $outer->whereExists(function ($q) use ($orgId, $today): void {
                    $q->selectRaw('1')
                        ->from('employee_positions')
                        ->join('positions', 'positions.id', '=', 'employee_positions.position_id')
                        ->whereColumn('employee_positions.employee_id', 'employees.id')
                        ->where('positions.organization_id', $orgId)
                        ->whereNull('employee_positions.deleted_at')
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('employee_positions.end_date')
                                ->orWhereDate('employee_positions.end_date', '>=', $today);
                        });
                })->orWhereExists(function ($q) use ($orgId, $today): void {
                    $q->selectRaw('1')
                        ->from('employee_assignments')
                        ->join('organizational_units', 'organizational_units.id', '=', 'employee_assignments.organizational_unit_id')
                        ->whereColumn('employee_assignments.employee_id', 'employees.id')
                        ->where('organizational_units.organization_id', $orgId)
                        ->whereNotNull('employee_assignments.organizational_unit_id')
                        ->whereNull('employee_assignments.deleted_at')
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('employee_assignments.end_date')
                                ->orWhereDate('employee_assignments.end_date', '>=', $today);
                        });
                })->orWhereExists(function ($q) use ($orgId, $today): void {
                    $q->selectRaw('1')
                        ->from('employee_assignments')
                        ->whereColumn('employee_assignments.employee_id', 'employees.id')
                        ->where('employee_assignments.organization_id', $orgId)
                        ->whereNull('employee_assignments.organizational_unit_id')
                        ->whereNull('employee_assignments.deleted_at')
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('employee_assignments.end_date')
                                ->orWhereDate('employee_assignments.end_date', '>=', $today);
                        });
                });
            });
        }

        $positionId = $parsed['positionId'];
        if ($positionId !== null) {
            $query->whereExists(function ($q) use ($positionId, $today): void {
                $q->selectRaw('1')
                    ->from('employee_positions')
                    ->whereColumn('employee_positions.employee_id', 'employees.id')
                    ->where('employee_positions.position_id', $positionId)
                    ->whereNull('employee_positions.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_positions.end_date')
                            ->orWhereDate('employee_positions.end_date', '>=', $today);
                    });
            });
        }

        if (is_int($unitId)) {
            $query->whereExists(function ($q) use ($unitId, $today): void {
                $q->selectRaw('1')
                    ->from('employee_assignments')
                    ->whereColumn('employee_assignments.employee_id', 'employees.id')
                    ->where('employee_assignments.organizational_unit_id', $unitId)
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    });
            });
        } elseif ($unitId === 'unassigned') {
            $query->whereNotExists(function ($q) use ($today, $allowedUnitIds): void {
                $q->selectRaw('1')
                    ->from('employee_assignments')
                    ->whereColumn('employee_assignments.employee_id', 'employees.id')
                    ->whereIn('employee_assignments.organizational_unit_id', $allowedUnitIds)
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    });
            });
        }

        $orgScope = $parsed['orgScope'];
        if ($orgScope === 'org_wide') {
            $query->whereExists(function ($q) use ($orgId, $today): void {
                $q->selectRaw('1')
                    ->from('employee_affiliations')
                    ->whereColumn('employee_affiliations.employee_id', 'employees.id')
                    ->where('employee_affiliations.organization_id', $orgId)
                    ->whereNull('employee_affiliations.root_unit_id')
                    ->whereNull('employee_affiliations.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        } elseif ($orgScope === 'branch_scoped') {
            $query->whereNotExists(function ($q) use ($orgId, $today): void {
                $q->selectRaw('1')
                    ->from('employee_affiliations')
                    ->whereColumn('employee_affiliations.employee_id', 'employees.id')
                    ->where('employee_affiliations.organization_id', $orgId)
                    ->whereNull('employee_affiliations.root_unit_id')
                    ->whereNull('employee_affiliations.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        }

        $search = $parsed['search'];
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('employees.id_number', 'like', $term)
                    ->orWhere('employees.first_name', 'like', $term)
                    ->orWhere('employees.last_name', 'like', $term)
                    ->orWhere('employees.middle_name', 'like', $term)
                    ->orWhere('employees.suffix', 'like', $term);
            });
        }

        $hireFrom = $parsed['hireFrom'] ?? null;
        $hireTo = $parsed['hireTo'] ?? null;
        if ($hireFrom !== null && $hireTo !== null) {
            $query->whereHas('currentEmployment', function ($q) use ($hireFrom, $hireTo): void {
                $q->whereDate('hire_date', '>=', $hireFrom)
                    ->whereDate('hire_date', '<=', $hireTo);
            });
        }

        if ($validated['sort'] === 'hire_date') {
            $query->leftJoin('employee_employments as ee_hire_sort', function ($join): void {
                $join->on('employees.id', '=', 'ee_hire_sort.employee_id')
                    ->where('ee_hire_sort.is_current', true)
                    ->whereNull('ee_hire_sort.deleted_at');
            });
        }

        $direction = $validated['direction'] === 'desc' ? 'desc' : 'asc';

        $sortColumn = match ($validated['sort']) {
            'first_name' => 'employees.first_name',
            'id_number' => 'employees.id_number',
            'id' => 'employees.id',
            'hire_date' => 'ee_hire_sort.hire_date',
            default => 'employees.last_name',
        };

        $query->orderBy($sortColumn, $direction);

        if ($validated['sort'] === 'hire_date') {
            $query->orderBy('employees.last_name', 'asc')
                ->orderBy('employees.first_name', 'asc')
                ->orderBy('employees.id', 'asc');
        } elseif ($validated['sort'] !== 'id') {
            $query->orderBy('employees.id', 'asc');
        }

        $paginator = $query->paginate($parsed['perPage'], ['*'], 'page', $parsed['page'])->withQueryString();

        $paginator->getCollection()->load([
            'user:id,employee_id,avatar_path',
            'currentEmployment' => function ($q): void {
                $q->select([
                    'employee_employments.id',
                    'employee_employments.employee_id',
                    'employee_employments.hire_date',
                    'employee_employments.separation_date',
                    'employee_employments.employment_status',
                    'employee_employments.separation_reason',
                    'employee_employments.notes',
                    'employee_employments.is_current',
                ]);
            },
            'positions' => function ($q) use ($today): void {
                $q->whereNull('deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    })
                    ->with(['position' => function ($q): void {
                        $q->select(['positions.id', 'positions.code', 'positions.title']);
                    }])
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
            'assignments' => function ($q) use ($today): void {
                $q->whereNull('deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    })
                    ->with([
                        'organizationalUnit:id,code,name,organization_id,unit_type_id',
                        'organizationalUnit.unitType:id,name',
                        'organization:id,code,name',
                    ])
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
            'contacts' => function ($q): void {
                $q->where('category', 'personal')
                    ->whereNull('deleted_at')
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
            'affiliations' => function ($q) use ($today, $orgId): void {
                $q->select([
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

        if ($branchRootId !== null) {
            $paginator->getCollection()->each(function (Employee $employee) use ($allowedUnitIds): void {
                $employee->setRelation('assignments', $employee->assignments
                    ->filter(fn (EmployeeAssignment $assignment): bool => $assignment->organizational_unit_id !== null
                        && in_array((int) $assignment->organizational_unit_id, $allowedUnitIds, true)
                        && $assignment->organizationalUnit !== null)
                    ->values());
            });
        }

        $mappedPaginator = $paginator->through(fn (Employee $employee): array => $this->mapEmployeeToRow($employee));
        $positionFilterOptions = Position::query()
            ->where('organization_id', $orgId)
            ->orderBy('title')
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn (Position $position): array => [
                'id' => (int) $position->id,
                'code' => (string) $position->code,
                'title' => (string) $position->title,
            ])
            ->all();
        $unitFilterOptions = $this->hrIndexUnitFilterCatalog->unitFilterOptionsForOrganization($orgId, $branchRootId);

        $branchScope = null;
        if ($branchRootId !== null) {
            $scopeUnit = OrganizationalUnit::query()
                ->whereKey($branchRootId)
                ->first(['id', 'code', 'name']);
            if ($scopeUnit !== null) {
                $branchScope = [
                    'id' => (int) $scopeUnit->id,
                    'code' => (string) $scopeUnit->code,
                    'name' => (string) $scopeUnit->name,
                ];
            }
        }

        return [
            'employees' => $mappedPaginator,
            'filters' => $filters,
            'positionFilterOptions' => $positionFilterOptions,
            'unitFilterOptions' => $unitFilterOptions,
            'organization' => [
                'id' => (int) $organization->id,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
            ],
            'branchScope' => $branchScope,
        ];
    }

    /**
     * Branch-picker users see the directory for the session root only (via current {@see EmployeeAffiliation} rows).
     * Others use the legacy org-wide visibility (positions / assignments).
     */
    private function resolveBranchRootIdForEmployeeIndex(Request $request, Organization $organization): ?int
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
     * @return array{
     *     id: int,
     *     display_name: string,
     *     id_number: string,
     *     avatar_url: string|null,
     *     is_org_wide: bool,
     *     positions: list<array{id: int, code: string, title: string, is_primary: bool}>,
     *     units: list<array{name: string, unit_type: string, code: string|null}>,
     *     contact: array{phone: string|null, email: string|null},
     * }
     */
    private function mapEmployeeToRow(Employee $employee): array
    {
        $positions = $employee->positions
            ->filter(fn ($ep) => $ep->position !== null)
            ->sort(function (EmployeePosition $a, EmployeePosition $b): int {
                if ($a->is_primary !== $b->is_primary) {
                    return $b->is_primary <=> $a->is_primary;
                }

                return strcmp($a->position->title, $b->position->title);
            })
            ->values()
            ->map(fn ($ep): array => [
                'id' => (int) $ep->position->id,
                'code' => (string) $ep->position->code,
                'title' => (string) $ep->position->title,
                'is_primary' => (bool) $ep->is_primary,
            ])
            ->all();

        $units = $employee->assignments
            ->map(function ($assignment): ?array {
                if ($assignment->organizational_unit_id !== null && $assignment->organizationalUnit !== null) {
                    $u = $assignment->organizationalUnit;

                    return [
                        'name' => (string) $u->name,
                        'unit_type' => $u->unitType !== null
                            ? (string) $u->unitType->name
                            : 'Unit',
                        'code' => filled($u->code) ? (string) $u->code : null,
                    ];
                }

                if ($assignment->organization_id !== null && $assignment->organization !== null) {
                    return [
                        'name' => 'Organization: '.(string) $assignment->organization->name,
                        'unit_type' => 'Organization',
                        'code' => filled($assignment->organization->code)
                            ? (string) $assignment->organization->code
                            : null,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();

        $phone = null;
        $primaryContact = $employee->contacts->firstWhere('is_primary', true);
        if ($primaryContact !== null && filled($primaryContact->contact_number)) {
            $phone = (string) $primaryContact->contact_number;
        } else {
            $withNumber = $employee->contacts->first(fn ($c) => filled($c->contact_number));
            if ($withNumber !== null) {
                $phone = (string) $withNumber->contact_number;
            }
        }

        $email = null;
        $withEmail = $employee->contacts->first(fn ($c) => filled($c->email));
        if ($withEmail !== null) {
            $email = (string) $withEmail->email;
        }

        $currentEmployment = $employee->currentEmployment;

        return [
            'id' => (int) $employee->id,
            'display_name' => $this->formatEmployeeDisplayName($employee),
            'id_number' => (string) $employee->id_number,
            'avatar_url' => $this->resolveAvatarUrl($employee),
            'is_org_wide' => $employee->affiliations->isNotEmpty(),
            'positions' => $positions,
            'units' => $units,
            'contact' => [
                'phone' => $phone,
                'email' => $email,
            ],
            'current_employment' => $currentEmployment === null
                ? null
                : $this->mapCurrentEmploymentForDirectoryRow($currentEmployment, $employee),
        ];
    }

    /**
     * Mirrors {@see EmployeesEmploymentHistoryController::mapEmploymentRow} for the current employment row only.
     *
     * @return array{
     *     id: int,
     *     hire_date: string,
     *     separation_date: string|null,
     *     employment_status: string,
     *     separation_reason: string|null,
     *     notes: string|null,
     *     tenure_days: int,
     *     employee: array{
     *         id: int,
     *         display_name: string,
     *         id_number: string,
     *         avatar_url: string|null,
     *         is_org_wide: bool
     *     }
     * }
     */
    private function mapCurrentEmploymentForDirectoryRow(EmployeeEmployment $employment, Employee $employee): array
    {
        $today = Carbon::now()->startOfDay();
        $hire = Carbon::parse($employment->hire_date)->startOfDay();
        $separation = $employment->separation_date !== null
            ? Carbon::parse($employment->separation_date)->startOfDay()
            : null;
        $end = $separation ?? $today->copy()->startOfDay();

        $daySpan = (int) $hire->diffInDays($end, false);
        $tenureDays = $daySpan >= 0 ? $daySpan + 1 : 1;

        return [
            'id' => (int) $employment->id,
            'hire_date' => Carbon::parse($employment->hire_date)->toDateString(),
            'hire_adjustment_max_date' => EmploymentHireAdjustmentBoundary::latestPermittedHireDateIsoForEmployment(
                (int) $employment->getKey(),
            ),
            'separation_date' => $employment->separation_date !== null
                ? Carbon::parse($employment->separation_date)->toDateString()
                : null,
            'employment_status' => (string) $employment->employment_status,
            'separation_reason' => $employment->separation_reason !== null && $employment->separation_reason !== ''
                ? (string) $employment->separation_reason
                : null,
            'notes' => $employment->notes !== null && $employment->notes !== ''
                ? (string) $employment->notes
                : null,
            'tenure_days' => $tenureDays,
            'employee' => [
                'id' => (int) $employee->id,
                'display_name' => $this->formatEmployeeDisplayName($employee),
                'id_number' => (string) $employee->id_number,
                'avatar_url' => $this->resolveAvatarUrl($employee),
                'is_org_wide' => $employee->affiliations->isNotEmpty(),
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
}
