<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAdminUsersRequest;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminUserActionAccessService;
use App\Services\BranchContextService;
use App\Services\HrIndexUnitFilterCatalog;
use App\Support\TeamHrEmployeeDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class AdminUsersIndexController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HrIndexUnitFilterCatalog $hrIndexUnitFilterCatalog,
    ) {}

    public function __invoke(IndexAdminUsersRequest $request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        /** @var AdminUserActionAccessService $accessService */
        $accessService = app(AdminUserActionAccessService::class);
        abort_unless($accessService->canAccessAdministration($user, $request), 403);
        $viewerCanManageRoles = $accessService->canManageAdminUserRoles($user);
        $viewerCanUseEmploymentStateFilter = $accessService->canUseEmploymentStateFilter($user);

        $viewerIsSuperAdmin = $user?->hasRole(Role::CODE_SUPER_ADMIN) ?? false;

        $validated = $request->validated();
        $view = (string) $validated['view'];
        $search = isset($validated['search']) && $validated['search'] !== ''
            ? (string) $validated['search']
            : '';
        $sort = (string) $validated['sort'];
        $direction = (string) $validated['direction'] === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $validated['per_page'];
        $page = (int) $validated['page'];
        $roleId = isset($validated['role_id']) ? (int) $validated['role_id'] : null;
        if (! $viewerIsSuperAdmin && $roleId !== null && $this->isSuperAdminRoleId($roleId)) {
            $roleId = null;
        }
        $orgScope = isset($validated['org_scope']) ? (string) $validated['org_scope'] : null;
        $accountStatus = (string) ($validated['account_status'] ?? 'all');
        $employmentState = (string) ($validated['employment_state'] ?? 'active');
        $employmentStatus = isset($validated['employment_status']) ? (string) $validated['employment_status'] : null;
        if (! $viewerCanUseEmploymentStateFilter) {
            $employmentState = 'active';
            $employmentStatus = null;
        }
        $organization = $this->branchContextService->defaultOrganization();
        $branchRootId = $this->resolveBranchRootIdForAdminUsers($request, $organization);
        $organizationId = $organization?->id;

        /** @var int|'unassigned'|null $unitFilter */
        $unitFilter = $validated['unit_id'] ?? null;
        $allowedUnitIds = $organizationId !== null
            ? $this->hrIndexUnitFilterCatalog->allowedUnitIds((int) $organizationId, $branchRootId)
            : [];

        if (is_int($unitFilter) && ! in_array($unitFilter, $allowedUnitIds, true)) {
            $unitFilter = null;
        }

        if (($unitFilter === 'unassigned' || is_int($unitFilter)) && $allowedUnitIds === []) {
            $unitFilter = null;
        }

        if ($employmentState === 'inactive') {
            $unitFilter = null;
        }

        $unitFilterOptions = $organizationId !== null
            ? $this->hrIndexUnitFilterCatalog->unitFilterOptionsForOrganization((int) $organizationId, $branchRootId)
            : [];

        $branchScopePayload = null;
        if ($branchRootId !== null) {
            $scopeUnit = OrganizationalUnit::query()
                ->whereKey($branchRootId)
                ->first(['id', 'code', 'name']);
            if ($scopeUnit !== null) {
                $branchScopePayload = [
                    'id' => (int) $scopeUnit->id,
                    'code' => (string) $scopeUnit->code,
                    'name' => (string) $scopeUnit->name,
                ];
            }
        }

        $organizationPayload = $organization !== null
            ? [
                'id' => (int) $organization->id,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
            ]
            : null;

        $rolesPaginator = $this->buildRolesPaginator(
            $search,
            $sort,
            $direction,
            $perPage,
            $page,
            $viewerIsSuperAdmin,
            $organizationId,
            $branchRootId,
            $unitFilter,
            $allowedUnitIds,
        );
        $usersPaginator = $this->buildUsersPaginator(
            $search,
            $sort,
            $direction,
            $perPage,
            $page,
            $roleId,
            $orgScope,
            $organizationId,
            $branchRootId,
            $viewerIsSuperAdmin,
            $unitFilter,
            $allowedUnitIds,
            $accountStatus,
            $employmentState,
            $employmentStatus,
        );
        $roleQuery = Role::query()->orderBy('name', 'asc')->orderBy('code', 'asc');
        if (! $viewerIsSuperAdmin) {
            $roleQuery->where('code', '<>', Role::CODE_SUPER_ADMIN);
        }
        $roleFilterOptions = $roleQuery
            ->get(['id', 'code', 'name'])
            ->map(fn (Role $role): array => [
                'id' => (int) $role->id,
                'code' => (string) $role->code,
                'name' => (string) $role->name,
            ])
            ->all();

        $branchOptions = $this->branchContextService
            ->branchesForPicker()
            ->map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ])
            ->all();

        return Inertia::render('Admin/Users', [
            'roles' => $rolesPaginator,
            'users' => $usersPaginator,
            'roleFilterOptions' => $roleFilterOptions,
            'unitFilterOptions' => $unitFilterOptions,
            'branchOptions' => $branchOptions,
            'viewerIsSuperAdmin' => $viewerIsSuperAdmin,
            'viewerCanManageRoles' => $viewerCanManageRoles,
            'viewerCanUseEmploymentStateFilter' => $viewerCanUseEmploymentStateFilter,
            'organization' => $organizationPayload,
            'branchScope' => $branchScopePayload,
            'filters' => [
                'view' => $view,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'role_id' => $roleId,
                'org_scope' => $orgScope,
                'unit_id' => $unitFilter,
                'account_status' => $accountStatus,
                'employment_state' => $employmentState,
                'employment_status' => $employmentStatus,
            ],
        ]);
    }

    private function buildRolesPaginator(
        string $search,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
        bool $viewerIsSuperAdmin,
        ?int $organizationId,
        ?int $branchRootId,
        int|string|null $unitFilter,
        array $allowedUnitIds,
    ) {
        $query = Role::query()
            ->select(['id', 'code', 'name', 'description']);

        if (! $viewerIsSuperAdmin) {
            $query->where('code', '<>', Role::CODE_SUPER_ADMIN)
                ->withCount([
                    'users as users_count' => function ($q) use ($organizationId, $branchRootId, $unitFilter, $allowedUnitIds): void {
                        $q->whereDoesntHave('roles', function ($roleQ): void {
                            $roleQ->where('roles.code', Role::CODE_SUPER_ADMIN);
                        });
                        $this->applyRolesUsersScopeConstraints(
                            $q,
                            $organizationId,
                            $branchRootId,
                            $unitFilter,
                            $allowedUnitIds,
                        );
                    },
                ])
                ->with([
                    'users' => function ($q) use ($organizationId, $branchRootId, $unitFilter, $allowedUnitIds): void {
                        $q->select(['users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.employee_id'])
                            ->whereDoesntHave('roles', function ($roleQ): void {
                                $roleQ->where('roles.code', Role::CODE_SUPER_ADMIN);
                            });
                        $this->applyRolesUsersScopeConstraints(
                            $q,
                            $organizationId,
                            $branchRootId,
                            $unitFilter,
                            $allowedUnitIds,
                        );
                        $q->orderBy('users.name')
                            ->orderBy('users.id');
                    },
                    'users.employee' => function ($q): void {
                        $q->select(['employees.id', 'employees.id_number']);
                    },
                ]);
        } else {
            $query->withCount([
                'users as users_count' => function ($q) use ($organizationId, $branchRootId, $unitFilter, $allowedUnitIds): void {
                    $this->applyRolesUsersScopeConstraints(
                        $q,
                        $organizationId,
                        $branchRootId,
                        $unitFilter,
                        $allowedUnitIds,
                    );
                },
            ])
                ->with([
                    'users' => function ($q) use ($organizationId, $branchRootId, $unitFilter, $allowedUnitIds): void {
                        $q->select(['users.id', 'users.name', 'users.email', 'users.avatar_path', 'users.employee_id']);
                        $this->applyRolesUsersScopeConstraints(
                            $q,
                            $organizationId,
                            $branchRootId,
                            $unitFilter,
                            $allowedUnitIds,
                        );
                        $q->orderBy('users.name')
                            ->orderBy('users.id');
                    },
                    'users.employee' => function ($q): void {
                        $q->select(['employees.id', 'employees.id_number']);
                    },
                ]);
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term, $viewerIsSuperAdmin): void {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('users', function ($userQ) use ($term, $viewerIsSuperAdmin): void {
                        if (! $viewerIsSuperAdmin) {
                            $userQ->whereDoesntHave('roles', function ($roleQ): void {
                                $roleQ->where('roles.code', Role::CODE_SUPER_ADMIN);
                            });
                        }
                        $userQ->where(function ($inner) use ($term): void {
                            $inner->where('users.name', 'like', $term)
                                ->orWhere('users.email', 'like', $term);
                        });
                    });
            });
        }

        $sortColumn = match ($sort) {
            'name' => 'name',
            'code' => 'code',
            'users_count' => 'users_count',
            'id' => 'id',
            default => 'name',
        };

        $query->orderBy($sortColumn, $direction);
        if ($sortColumn !== 'id') {
            $query->orderBy('id', 'asc');
        }

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => (int) $role->id,
                'code' => (string) $role->code,
                'name' => (string) $role->name,
                'description' => $role->description !== null ? (string) $role->description : null,
                'users_count' => (int) $role->users_count,
                'users' => $role->users
                    ->map(fn (User $roleUser): array => [
                        'id' => (int) $roleUser->id,
                        'name' => (string) $roleUser->name,
                        'email' => (string) $roleUser->email,
                        'avatar_url' => $this->resolveAvatarUrl($roleUser),
                        'employee_number' => $roleUser->employee?->id_number !== null
                            ? (string) $roleUser->employee->id_number
                            : null,
                    ])
                    ->all(),
            ]);
    }

    /**
     * @param  Builder<User>|BelongsToMany<User>  $query
     * @param  int|'unassigned'|null  $unitFilter
     * @param  list<int>  $allowedUnitIds
     */
    private function applyRolesUsersScopeConstraints(
        Builder|BelongsToMany $query,
        ?int $organizationId,
        ?int $branchRootId,
        int|string|null $unitFilter,
        array $allowedUnitIds,
    ): void {
        $today = now()->toDateString();

        $query->whereNotNull('users.employee_id');

        if ($organizationId !== null && $branchRootId !== null) {
            $query->whereHas('employee.affiliations', function ($q) use ($organizationId, $branchRootId, $today): void {
                $q->where('employee_affiliations.organization_id', $organizationId)
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
        }

        if ($unitFilter === null || $allowedUnitIds === []) {
            return;
        }

        if (is_int($unitFilter)) {
            $query->whereHas('employee.assignments', function ($q) use ($unitFilter, $today): void {
                $q->where('employee_assignments.organizational_unit_id', $unitFilter)
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    });
            });

            return;
        }

        if ($unitFilter !== 'unassigned') {
            return;
        }

        $query->whereHas('employee', function ($eq) use ($allowedUnitIds, $today): void {
            $eq->whereNotExists(function ($sub) use ($allowedUnitIds, $today): void {
                $sub->selectRaw('1')
                    ->from('employee_assignments')
                    ->whereColumn('employee_assignments.employee_id', 'employees.id')
                    ->whereIn('employee_assignments.organizational_unit_id', $allowedUnitIds)
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    });
            });
        });
    }

    private function buildUsersPaginator(
        string $search,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
        ?int $roleId,
        ?string $orgScope,
        ?int $organizationId,
        ?int $branchRootId,
        bool $viewerIsSuperAdmin,
        int|string|null $unitFilter,
        array $allowedUnitIds,
        string $accountStatus,
        string $employmentState,
        ?string $employmentStatus,
    ) {
        if ($accountStatus === 'all') {
            return $this->buildAllUsersPaginator(
                $search,
                $sort,
                $direction,
                $perPage,
                $page,
                $roleId,
                $orgScope,
                $organizationId,
                $branchRootId,
                $viewerIsSuperAdmin,
                $unitFilter,
                $allowedUnitIds,
                $employmentState,
                $employmentStatus,
            );
        }

        if ($accountStatus === 'no_account') {
            return $this->buildEmployeesWithoutAccountPaginator(
                $search,
                $sort,
                $direction,
                $perPage,
                $page,
                $orgScope,
                $organizationId,
                $branchRootId,
                $unitFilter,
                $allowedUnitIds,
                $employmentState,
                $employmentStatus,
            );
        }

        return $this->buildUsersWithAccountPaginator(
            $search,
            $sort,
            $direction,
            $perPage,
            $page,
            $roleId,
            $orgScope,
            $organizationId,
            $branchRootId,
            $viewerIsSuperAdmin,
            $unitFilter,
            $allowedUnitIds,
            true,
            $employmentState,
            $employmentStatus,
        );
    }

    private function buildUsersWithAccountPaginator(
        string $search,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
        ?int $roleId,
        ?string $orgScope,
        ?int $organizationId,
        ?int $branchRootId,
        bool $viewerIsSuperAdmin,
        int|string|null $unitFilter,
        array $allowedUnitIds,
        bool $linkedEmployeesOnly,
        string $employmentState,
        ?string $employmentStatus,
    ) {
        $query = User::query()
            ->select(['id', 'name', 'email', 'employee_id']);

        if (! $viewerIsSuperAdmin) {
            $query->whereDoesntHave('roles', function ($q): void {
                $q->where('roles.code', Role::CODE_SUPER_ADMIN);
            });
        }

        $query->with([
            'roles' => function ($q): void {
                $q->select(['roles.id', 'roles.code', 'roles.name'])
                    ->orderBy('roles.name')
                    ->orderBy('roles.id');
            },
            'employee' => function ($q): void {
                $q->select(['employees.id', 'employees.id_number']);
            },
            'employee.currentEmployment' => function ($q): void {
                $q->select([
                    'employee_employments.id',
                    'employee_employments.employee_id',
                    'employee_employments.employment_status',
                    'employee_employments.is_current',
                ]);
            },
            'employee.employments' => function ($q): void {
                $q->select([
                    'employee_employments.id',
                    'employee_employments.employee_id',
                    'employee_employments.hire_date',
                    'employee_employments.separation_date',
                    'employee_employments.employment_status',
                    'employee_employments.is_current',
                ])->orderByDesc('employee_employments.is_current')
                    ->orderByDesc('employee_employments.separation_date')
                    ->orderByDesc('employee_employments.hire_date')
                    ->orderByDesc('employee_employments.id');
            },
            'employee.affiliations' => function ($q) use ($employmentState): void {
                $today = now()->toDateString();
                $q->select([
                    'employee_affiliations.id',
                    'employee_affiliations.employee_id',
                    'employee_affiliations.organization_id',
                    'employee_affiliations.root_unit_id',
                    'employee_affiliations.end_date',
                    'employee_affiliations.deleted_at',
                ])
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when($employmentState === 'active', function ($q2) use ($today): void {
                        $q2->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    })
                    ->with([
                        'rootUnit:id,code,name',
                    ]);
            },
            'employee.assignments' => function ($q) use ($employmentState): void {
                $today = now()->toDateString();
                $q->select([
                    'employee_assignments.id',
                    'employee_assignments.employee_id',
                    'employee_assignments.organization_id',
                    'employee_assignments.organizational_unit_id',
                    'employee_assignments.end_date',
                    'employee_assignments.deleted_at',
                ])
                    ->whereNull('employee_assignments.deleted_at')
                    ->when($employmentState === 'active', function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    })
                    ->with([
                        'organizationalUnit:id,code,name',
                    ]);
            },
        ])
            ->withCount('roles');

        if ($organizationId !== null && $branchRootId !== null) {
            $today = now()->toDateString();
            $query->whereHas('employee.affiliations', function ($q) use ($organizationId, $branchRootId, $today, $employmentState): void {
                $q->where('employee_affiliations.organization_id', $organizationId)
                    ->where(function ($q2) use ($branchRootId): void {
                        $q2->where('employee_affiliations.root_unit_id', $branchRootId)
                            ->orWhereNull('employee_affiliations.root_unit_id');
                    })
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when($employmentState === 'active', function ($q3) use ($today): void {
                        $q3->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        }

        if ($roleId !== null) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId));
        }

        $this->applyUsersTabUnitFilter($query, $unitFilter, $allowedUnitIds);

        if ($orgScope === 'org_wide') {
            $today = now()->toDateString();
            $query->whereHas('employee.affiliations', function ($q) use ($today, $organizationId, $employmentState): void {
                if ($organizationId !== null) {
                    $q->where('employee_affiliations.organization_id', $organizationId);
                }
                $q->whereNull('employee_affiliations.root_unit_id')
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when($employmentState === 'active', function ($q2) use ($today): void {
                        $q2->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        } elseif ($orgScope === 'not_org_wide') {
            $today = now()->toDateString();
            $query->whereDoesntHave('employee.affiliations', function ($q) use ($today, $organizationId, $employmentState): void {
                if ($organizationId !== null) {
                    $q->where('employee_affiliations.organization_id', $organizationId);
                }
                $q->whereNull('employee_affiliations.root_unit_id')
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when($employmentState === 'active', function ($q2) use ($today): void {
                        $q2->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        }

        if ($linkedEmployeesOnly) {
            $query->whereNotNull('employee_id', 'and');
        }

        $this->applyUsersEmploymentStateFilter($query, $employmentState);

        if ($employmentStatus !== null) {
            if ($employmentState === 'active') {
                $query->whereHas('employee.currentEmployment', function ($q) use ($employmentStatus): void {
                    $q->where('employee_employments.employment_status', $employmentStatus);
                });
            } else {
                $query->where(function ($q) use ($employmentStatus): void {
                    $q->whereHas('employee.currentEmployment', function ($eq) use ($employmentStatus): void {
                        $eq->where('employee_employments.employment_status', $employmentStatus);
                    })->orWhere(function ($missingCurrent) use ($employmentStatus): void {
                        $missingCurrent->whereDoesntHave('employee.currentEmployment')
                            ->whereHas('employee.employments', function ($eq) use ($employmentStatus): void {
                                $eq->where('employee_employments.employment_status', $employmentStatus);
                            });
                    });
                });
            }
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term, $viewerIsSuperAdmin): void {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhereHas('roles', function ($roleQ) use ($term, $viewerIsSuperAdmin): void {
                        if (! $viewerIsSuperAdmin) {
                            $roleQ->where('roles.code', '<>', Role::CODE_SUPER_ADMIN);
                        }
                        $roleQ->where(function ($inner) use ($term): void {
                            $inner->where('roles.name', 'like', $term)
                                ->orWhere('roles.code', 'like', $term);
                        });
                    });
            });
        }

        $sortColumn = match ($sort) {
            'name' => 'name',
            'code' => 'email',
            'users_count' => 'roles_count',
            'id' => 'id',
            default => 'name',
        };

        $query->orderBy($sortColumn, $direction);
        if ($sortColumn !== 'id') {
            $query->orderBy('id', 'asc');
        }

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => (int) $user->id,
                'user_id' => (int) $user->id,
                'has_account' => true,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'employee_number' => $user->employee?->id_number !== null
                    ? (string) $user->employee->id_number
                    : null,
                'roles_count' => (int) $user->roles_count,
                'roles' => $user->roles
                    ->map(fn (Role $role): array => [
                        'id' => (int) $role->id,
                        'code' => (string) $role->code,
                        'name' => (string) $role->name,
                    ])
                    ->all(),
                'employment_status' => $this->resolveEmployeeEmploymentStatus($user->employee),
                ...$this->resolveUserScopeData($user),
            ]);
    }

    private function buildAllUsersPaginator(
        string $search,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
        ?int $roleId,
        ?string $orgScope,
        ?int $organizationId,
        ?int $branchRootId,
        bool $viewerIsSuperAdmin,
        int|string|null $unitFilter,
        array $allowedUnitIds,
        string $employmentState,
        ?string $employmentStatus,
    ): LengthAwarePaginator {
        $withAccountRows = collect(
            $this->buildUsersWithAccountPaginator(
                $search,
                $sort,
                $direction,
                5000,
                1,
                $roleId,
                $orgScope,
                $organizationId,
                $branchRootId,
                $viewerIsSuperAdmin,
                $unitFilter,
                $allowedUnitIds,
                false,
                $employmentState,
                $employmentStatus,
            )->items()
        );

        $withoutAccountRows = collect(
            $this->buildEmployeesWithoutAccountPaginator(
                $search,
                $sort,
                $direction,
                5000,
                1,
                $orgScope,
                $organizationId,
                $branchRootId,
                $unitFilter,
                $allowedUnitIds,
                $employmentState,
                $employmentStatus,
            )->items()
        );

        $rows = $withAccountRows
            ->concat($withoutAccountRows)
            ->sort(function (array $a, array $b) use ($sort, $direction): int {
                $compare = match ($sort) {
                    'code' => strcmp((string) ($a['employee_number'] ?? ''), (string) ($b['employee_number'] ?? '')),
                    'users_count' => ((int) ($a['roles_count'] ?? 0)) <=> ((int) ($b['roles_count'] ?? 0)),
                    'id' => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)),
                    default => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')),
                };

                if ($compare === 0) {
                    $compare = ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }

                return $direction === 'desc' ? -$compare : $compare;
            })
            ->values();

        $total = $rows->count();
        $offset = max(0, ($page - 1) * $perPage);
        $sliced = $rows->slice($offset, $perPage)->values()->all();

        return new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => 'page',
            ],
        );
    }

    private function buildEmployeesWithoutAccountPaginator(
        string $search,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
        ?string $orgScope,
        ?int $organizationId,
        ?int $branchRootId,
        int|string|null $unitFilter,
        array $allowedUnitIds,
        string $employmentState,
        ?string $employmentStatus,
    ) {
        $query = Employee::query()
            ->select([
                'employees.id',
                'employees.id_number',
                'employees.first_name',
                'employees.middle_name',
                'employees.last_name',
                'employees.suffix',
            ])
            ->with([
                'currentEmployment' => function ($q): void {
                    $q->select([
                        'employee_employments.id',
                        'employee_employments.employee_id',
                        'employee_employments.employment_status',
                        'employee_employments.is_current',
                    ]);
                },
            ])
            ->with([
                'employments' => function ($q): void {
                    $q->select([
                        'employee_employments.id',
                        'employee_employments.employee_id',
                        'employee_employments.hire_date',
                        'employee_employments.separation_date',
                        'employee_employments.employment_status',
                        'employee_employments.is_current',
                    ])->orderByDesc('employee_employments.is_current')
                        ->orderByDesc('employee_employments.separation_date')
                        ->orderByDesc('employee_employments.hire_date')
                        ->orderByDesc('employee_employments.id');
                },
            ])
            ->whereDoesntHave('user')
            ->with([
                'affiliations' => function ($q) use ($employmentState): void {
                    $today = now()->toDateString();
                    $q->select([
                        'employee_affiliations.id',
                        'employee_affiliations.employee_id',
                        'employee_affiliations.organization_id',
                        'employee_affiliations.root_unit_id',
                        'employee_affiliations.end_date',
                        'employee_affiliations.deleted_at',
                    ])
                        ->whereNull('employee_affiliations.deleted_at')
                        ->when($employmentState === 'active', function ($q2) use ($today): void {
                            $q2->whereNull('employee_affiliations.end_date')
                                ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                        })
                        ->with([
                            'rootUnit:id,code,name',
                        ]);
                },
                'assignments' => function ($q) use ($employmentState): void {
                    $today = now()->toDateString();
                    $q->select([
                        'employee_assignments.id',
                        'employee_assignments.employee_id',
                        'employee_assignments.organization_id',
                        'employee_assignments.organizational_unit_id',
                        'employee_assignments.end_date',
                        'employee_assignments.deleted_at',
                    ])
                        ->whereNull('employee_assignments.deleted_at')
                        ->when($employmentState === 'active', function ($q2) use ($today): void {
                            $q2->whereNull('employee_assignments.end_date')
                                ->orWhereDate('employee_assignments.end_date', '>=', $today);
                        })
                        ->with([
                            'organizationalUnit:id,code,name',
                        ]);
                },
            ]);

        if ($organizationId !== null && $branchRootId !== null) {
            $today = now()->toDateString();
            $query->whereHas('affiliations', function ($q) use ($organizationId, $branchRootId, $today, $employmentState): void {
                $q->where('employee_affiliations.organization_id', $organizationId)
                    ->where(function ($q2) use ($branchRootId): void {
                        $q2->where('employee_affiliations.root_unit_id', $branchRootId)
                            ->orWhereNull('employee_affiliations.root_unit_id');
                    })
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when($employmentState === 'active', function ($q3) use ($today): void {
                        $q3->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        }

        $this->applyEmployeesWithoutAccountUnitFilter($query, $unitFilter, $allowedUnitIds);

        if ($orgScope === 'org_wide') {
            $today = now()->toDateString();
            $query->whereHas('affiliations', function ($q) use ($today, $organizationId, $employmentState): void {
                if ($organizationId !== null) {
                    $q->where('employee_affiliations.organization_id', $organizationId);
                }
                $q->whereNull('employee_affiliations.root_unit_id')
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when($employmentState === 'active', function ($q2) use ($today): void {
                        $q2->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        } elseif ($orgScope === 'not_org_wide') {
            $today = now()->toDateString();
            $query->whereDoesntHave('affiliations', function ($q) use ($today, $organizationId, $employmentState): void {
                if ($organizationId !== null) {
                    $q->where('employee_affiliations.organization_id', $organizationId);
                }
                $q->whereNull('employee_affiliations.root_unit_id')
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when($employmentState === 'active', function ($q2) use ($today): void {
                        $q2->whereNull('employee_affiliations.end_date')
                            ->orWhereDate('employee_affiliations.end_date', '>=', $today);
                    });
            });
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('employees.id_number', 'like', $term)
                    ->orWhere('employees.first_name', 'like', $term)
                    ->orWhere('employees.middle_name', 'like', $term)
                    ->orWhere('employees.last_name', 'like', $term)
                    ->orWhere('employees.suffix', 'like', $term);
            });
        }

        $this->applyEmployeesEmploymentStateFilter($query, $employmentState);

        if ($employmentStatus !== null) {
            if ($employmentState === 'active') {
                $query->whereHas('currentEmployment', function ($q) use ($employmentStatus): void {
                    $q->where('employee_employments.employment_status', $employmentStatus);
                });
            } else {
                $query->where(function ($q) use ($employmentStatus): void {
                    $q->whereHas('currentEmployment', function ($eq) use ($employmentStatus): void {
                        $eq->where('employee_employments.employment_status', $employmentStatus);
                    })->orWhere(function ($missingCurrent) use ($employmentStatus): void {
                        $missingCurrent->whereDoesntHave('currentEmployment')
                            ->whereHas('employments', function ($eq) use ($employmentStatus): void {
                                $eq->where('employee_employments.employment_status', $employmentStatus);
                            });
                    });
                });
            }
        }

        $sortColumn = match ($sort) {
            'name' => 'employees.last_name',
            'code' => 'employees.id_number',
            'users_count' => 'employees.id_number',
            'id' => 'employees.id',
            default => 'employees.last_name',
        };

        $query->orderBy($sortColumn, $direction);
        if ($sortColumn !== 'employees.id') {
            $query->orderBy('employees.id', 'asc');
        }

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                'id' => (int) $employee->id,
                'user_id' => null,
                'has_account' => false,
                'name' => TeamHrEmployeeDisplay::fullName($employee),
                'email' => 'No account',
                'employee_number' => $employee->id_number !== null
                    ? (string) $employee->id_number
                    : null,
                'roles_count' => 0,
                'roles' => [],
                'employment_status' => $this->resolveEmployeeEmploymentStatus($employee),
                ...$this->resolveEmployeeScopeData($employee),
            ]);
    }

    private function resolveEmployeeEmploymentStatus(?Employee $employee): ?string
    {
        if ($employee === null) {
            return null;
        }

        $current = $employee->currentEmployment;
        if ($current !== null) {
            return $current->employment_status;
        }

        $latest = $employee->relationLoaded('employments')
            ? $employee->employments->first()
            : $employee->employments()
                ->orderByDesc('is_current')
                ->orderByDesc('separation_date')
                ->orderByDesc('hire_date')
                ->orderByDesc('id')
                ->first();

        return $latest?->employment_status;
    }

    private function applyUsersEmploymentStateFilter(Builder $query, string $employmentState): void
    {
        if ($employmentState === 'inactive') {
            $query->whereNotNull('employee_id')
                ->where(function ($q): void {
                    $q->whereHas('employee.currentEmployment', function ($eq): void {
                        $eq->where('employee_employments.employment_status', '<>', EmployeeEmployment::STATUS_ACTIVE);
                    })->orWhereDoesntHave('employee.currentEmployment');
                });

            return;
        }

        $query->where(function ($q): void {
            $q->whereNull('employee_id')
                ->orWhereHas('employee.currentEmployment', function ($eq): void {
                    $eq->where('employee_employments.employment_status', EmployeeEmployment::STATUS_ACTIVE);
                });
        });
    }

    private function applyEmployeesEmploymentStateFilter(Builder $query, string $employmentState): void
    {
        if ($employmentState === 'inactive') {
            $query->where(function ($q): void {
                $q->whereHas('currentEmployment', function ($eq): void {
                    $eq->where('employee_employments.employment_status', '<>', EmployeeEmployment::STATUS_ACTIVE);
                })->orWhereDoesntHave('currentEmployment');
            });

            return;
        }

        $query->whereHas('currentEmployment', function ($q): void {
            $q->where('employee_employments.employment_status', EmployeeEmployment::STATUS_ACTIVE);
        });
    }

    /**
     * @return array{
     *     scope_summary: string,
     *     assigned_branches: list<array{id: int, code: string, name: string}>,
     *     assigned_units: list<array{id: int, code: string, name: string}>,
     * }
     */
    /**
     * @param  int|'unassigned'|null  $unitFilter
     * @param  list<int>  $allowedUnitIds
     */
    private function applyUsersTabUnitFilter(Builder $query, int|string|null $unitFilter, array $allowedUnitIds): void
    {
        if ($unitFilter === null || $allowedUnitIds === []) {
            return;
        }

        $today = now()->toDateString();

        if (is_int($unitFilter)) {
            $query->whereHas('employee.assignments', function ($q) use ($unitFilter, $today): void {
                $q->where('employee_assignments.organizational_unit_id', $unitFilter)
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    });
            });

            return;
        }

        if ($unitFilter !== 'unassigned') {
            return;
        }

        $query->where(function ($outer) use ($allowedUnitIds, $today): void {
            $outer->whereNull('employee_id')
                ->orWhereHas('employee', function ($eq) use ($allowedUnitIds, $today): void {
                    $eq->whereNotExists(function ($sub) use ($allowedUnitIds, $today): void {
                        $sub->selectRaw('1')
                            ->from('employee_assignments')
                            ->whereColumn('employee_assignments.employee_id', 'employees.id')
                            ->whereIn('employee_assignments.organizational_unit_id', $allowedUnitIds)
                            ->whereNull('employee_assignments.deleted_at')
                            ->where(function ($q2) use ($today): void {
                                $q2->whereNull('employee_assignments.end_date')
                                    ->orWhereDate('employee_assignments.end_date', '>=', $today);
                            });
                    });
                });
        });
    }

    /**
     * @param  int|'unassigned'|null  $unitFilter
     * @param  list<int>  $allowedUnitIds
     */
    private function applyEmployeesWithoutAccountUnitFilter(Builder $query, int|string|null $unitFilter, array $allowedUnitIds): void
    {
        if ($unitFilter === null || $allowedUnitIds === []) {
            return;
        }

        $today = now()->toDateString();

        if (is_int($unitFilter)) {
            $query->whereHas('assignments', function ($q) use ($unitFilter, $today): void {
                $q->where('employee_assignments.organizational_unit_id', $unitFilter)
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    });
            });

            return;
        }

        if ($unitFilter !== 'unassigned') {
            return;
        }

        $query->whereNotExists(function ($sub) use ($allowedUnitIds, $today): void {
            $sub->selectRaw('1')
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

    private function resolveUserScopeData(User $user): array
    {
        return $this->resolveEmployeeScopeData($user->employee);
    }

    private function resolveEmployeeScopeData(?Employee $employee): array
    {
        if ($employee === null) {
            return [
                'scope_summary' => 'No employee profile linked',
                'assigned_branches' => [],
                'assigned_units' => [],
            ];
        }

        $affiliations = $employee->affiliations ?? collect();
        $assignments = $employee->assignments ?? collect();

        $hasOrgWideAffiliation = $affiliations->contains(
            fn (EmployeeAffiliation $affiliation): bool => $affiliation->root_unit_id === null
        );

        $assignedBranches = $affiliations
            ->filter(fn (EmployeeAffiliation $affiliation): bool => $affiliation->rootUnit !== null)
            ->map(fn (EmployeeAffiliation $affiliation): array => [
                'id' => (int) $affiliation->rootUnit->id,
                'code' => (string) $affiliation->rootUnit->code,
                'name' => (string) $affiliation->rootUnit->name,
            ])
            ->unique('id')
            ->values()
            ->all();

        $assignedUnits = $assignments
            ->filter(fn (EmployeeAssignment $assignment): bool => $assignment->organizationalUnit !== null)
            ->map(fn (EmployeeAssignment $assignment): array => [
                'id' => (int) $assignment->organizationalUnit->id,
                'code' => (string) $assignment->organizationalUnit->code,
                'name' => (string) $assignment->organizationalUnit->name,
            ])
            ->unique('id')
            ->values()
            ->all();

        $scopeSummary = match (true) {
            $hasOrgWideAffiliation => 'Org-wide',
            count($assignedBranches) > 0 => sprintf(
                '%d Branch/es',
                count($assignedBranches)
            ),
            count($assignedUnits) > 0 => sprintf(
                '%d unit%s',
                count($assignedUnits),
                count($assignedUnits) === 1 ? '' : 's'
            ),
            default => 'No explicit scope assigned',
        };

        return [
            'scope_summary' => $scopeSummary,
            'assigned_branches' => $assignedBranches,
            'assigned_units' => $assignedUnits,
        ];
    }

    private function resolveAvatarUrl(User $user): ?string
    {
        $avatarPath = $user->avatar_path;
        if ($avatarPath === null || $avatarPath === '') {
            return null;
        }

        return asset('storage/'.$avatarPath);
    }

    private function resolveBranchRootIdForAdminUsers(Request $request, ?Organization $organization): ?int
    {
        $user = $request->user();
        if ($user === null || ! $user->mustSelectBranch()) {
            return null;
        }

        if ($organization === null) {
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

    private function isSuperAdminRoleId(int $roleId): bool
    {
        return Role::query()
            ->whereKey($roleId)
            ->where('code', Role::CODE_SUPER_ADMIN)
            ->exists();
    }
}
