<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexEmployeeSchedulesRequest;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use App\Support\EmployeeBranchDirectoryFilter;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleAssignmentController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
    ) {}

    public function __invoke(IndexEmployeeSchedulesRequest $request): Response
    {
        $user = $request->user();

        abort_unless(
            $this->scheduleAssignmentAccessService->allows($user, $request),
            403,
        );

        $organization = $this->branchContextService->defaultOrganization();

        if ($organization === null) {
            return Inertia::render('Attendance/ScheduleAssignment', [
                'scheduleTemplateOptions' => [],
                'positionFilterOptions' => [],
                'employees' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'links' => [],
                ],
                'filters' => [
                    'search' => '',
                    'sort' => 'last_name',
                    'direction' => 'asc',
                    'per_page' => 15,
                    'position_id' => null,
                    'org_scope' => null,
                    'attendance_id_filter' => null,
                    'work_schedule_filter' => null,
                    'unit_filter' => null,
                ],
                'unitFilterOptions' => [],
                'organization' => null,
                'branchScope' => null,
            ]);
        }

        $validated = $request->validated();

        $perPage = (int) $validated['per_page'];
        $page = (int) $validated['page'];
        $search = (string) ($validated['search'] ?? '');
        $positionId = isset($validated['position_id']) ? (int) $validated['position_id'] : null;
        $orgScope = $validated['org_scope'] ?? null;
        $attendanceIdFilter = $validated['attendance_id_filter'] ?? null;
        $workScheduleFilter = $validated['work_schedule_filter'] ?? null;
        $unitFilter = $validated['unit_filter'] ?? null;

        $today = now()->toDateString();
        $orgId = (int) $organization->id;

        $branchRootId = $this->scheduleAssignmentAccessService->resolveBranchRootForEmployeeDirectory($request, $organization);
        $allowedUnitIds = $branchRootId !== null
            ? $this->scheduleAssignmentAccessService->collectSubtreeUnitIds($orgId, $branchRootId)
            : [];

        if (
            $branchRootId !== null
            && $unitFilter !== null
            && ctype_digit((string) $unitFilter)
            && ! in_array((int) $unitFilter, $allowedUnitIds, true)
        ) {
            $unitFilter = null;
        }

        $query = Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.middle_name',
                'employees.last_name',
                'employees.suffix',
                'employees.id_number',
                'employees.attendance_id',
                'employees.work_schedule_template_id',
            ])
            ->whereNull('employees.deleted_at');

        EmployeeBranchDirectoryFilter::apply($query, $orgId, $branchRootId, $today);

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('employees.id_number', 'like', $term)
                    ->orWhere('employees.attendance_id', 'like', $term)
                    ->orWhere('employees.first_name', 'like', $term)
                    ->orWhere('employees.last_name', 'like', $term)
                    ->orWhere('employees.middle_name', 'like', $term)
                    ->orWhere('employees.suffix', 'like', $term);
            });
        }

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

        if ($attendanceIdFilter === 'has') {
            $query->whereNotNull('employees.attendance_id')
                ->whereRaw('TRIM(employees.attendance_id) <> ?', ['']);
        } elseif ($attendanceIdFilter === 'missing') {
            $query->where(function ($q): void {
                $q->whereNull('employees.attendance_id')
                    ->orWhereRaw('TRIM(employees.attendance_id) = ?', ['']);
            });
        }

        if ($workScheduleFilter === 'assigned') {
            $query->whereNotNull('employees.work_schedule_template_id');
        } elseif ($workScheduleFilter === 'unassigned') {
            $query->whereNull('employees.work_schedule_template_id');
        }

        if ($unitFilter === 'organization') {
            $query->whereExists(function ($q) use ($orgId, $today): void {
                $q->selectRaw('1')
                    ->from('employee_assignments')
                    ->whereColumn('employee_assignments.employee_id', 'employees.id')
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    })
                    ->where('employee_assignments.organization_id', $orgId)
                    ->whereNull('employee_assignments.organizational_unit_id');
            });
        } elseif ($unitFilter !== null && ctype_digit((string) $unitFilter)) {
            $filterUnitId = (int) $unitFilter;
            $query->whereExists(function ($q) use ($filterUnitId, $today): void {
                $q->selectRaw('1')
                    ->from('employee_assignments')
                    ->whereColumn('employee_assignments.employee_id', 'employees.id')
                    ->whereNull('employee_assignments.deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('employee_assignments.end_date')
                            ->orWhereDate('employee_assignments.end_date', '>=', $today);
                    })
                    ->where('employee_assignments.organizational_unit_id', $filterUnitId);
            });
        }

        $sortColumn = match ($validated['sort']) {
            'first_name' => 'employees.first_name',
            'id_number' => 'employees.id_number',
            'id' => 'employees.id',
            default => 'employees.last_name',
        };

        $direction = $validated['direction'] === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $direction);
        if ($validated['sort'] !== 'id') {
            $query->orderBy('employees.id', 'asc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        $paginator->getCollection()->load([
            'user:id,employee_id,avatar_path',
            'workScheduleTemplate:id,organization_id,name,is_active',
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
                        'organizationalUnit:id,code,name,unit_type_id',
                        'organizationalUnit.unitType:id,name,color',
                        'organization:id,code,name',
                    ])
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
                $employee->setRelation(
                    'assignments',
                    $employee->assignments
                        ->filter(fn (EmployeeAssignment $assignment): bool => $assignment->organizational_unit_id !== null
                            && in_array((int) $assignment->organizational_unit_id, $allowedUnitIds, true)
                            && $assignment->organizationalUnit !== null)
                        ->values(),
                );
            });
        }

        $mappedPaginator = $paginator->through(fn (Employee $employee): array => $this->mapEmployeeRow($employee));

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

        $templateOptions = WorkScheduleTemplate::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(static fn (WorkScheduleTemplate $template): array => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'is_active' => (bool) $template->is_active,
            ])
            ->all();
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

        $unitFilterOptions = $this->unitFilterOptions($request, $organization, $branchRootId);

        return Inertia::render('Attendance/ScheduleAssignment', [
            'scheduleTemplateOptions' => $templateOptions,
            'positionFilterOptions' => $positionFilterOptions,
            'unitFilterOptions' => $unitFilterOptions,
            'employees' => $mappedPaginator,
            'filters' => [
                'search' => $search,
                'sort' => $validated['sort'],
                'direction' => $validated['direction'],
                'per_page' => $perPage,
                'position_id' => $positionId,
                'org_scope' => $orgScope,
                'attendance_id_filter' => $attendanceIdFilter,
                'work_schedule_filter' => $workScheduleFilter,
                'unit_filter' => $unitFilter,
            ],
            'organization' => [
                'id' => $organization->id,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
            ],
            'branchScope' => $branchScope,
        ]);
    }

    /**
     * Dropdown options for the unit (organizational unit / org-level) toolbar filter.
     *
     * @return list<array{value: string, label: string, code: string|null}>
     */
    private function unitFilterOptions(IndexEmployeeSchedulesRequest $request, Organization $organization, ?int $branchRootId): array
    {
        $user = $request->user();
        $orgId = (int) $organization->id;

        $q = OrganizationalUnit::query()
            ->where('organization_id', $orgId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('code')
            ->orderBy('name');

        if ($branchRootId !== null) {
            $allowed = $this->scheduleAssignmentAccessService->collectSubtreeUnitIds($orgId, $branchRootId);
            $q->whereIn('id', $allowed);
        }

        $options = [['value' => 'all', 'label' => 'All units', 'code' => null]];

        $canOrganization = $user !== null && $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);

        if ($canOrganization) {
            $options[] = ['value' => 'organization', 'label' => 'Organization', 'code' => null];
        }

        foreach ($q->get(['id', 'code', 'name']) as $unit) {
            $code = filled($unit->code) ? (string) $unit->code : '';
            $name = (string) $unit->name;

            $options[] = [
                'value' => (string) $unit->id,
                'label' => $name,
                'code' => $code !== '' ? $code : null,
            ];
        }

        return $options;
    }

    /**
     * @return array{
     *     id: int,
     *     display_name: string,
     *     id_number: string,
     *     attendance_id: string|null,
     *     avatar_url: string|null,
     *     units: list<array{name: string, unit_type: string, code: string|null, is_primary: bool, unit_type_color: string|null}>,
     *     work_schedule: array{id: int, name: string, is_active: bool}|null,
     *     is_org_wide: bool,
     *     positions: list<array{id: int, code: string, title: string, is_primary: bool}>
     * }
     */
    private function mapEmployeeRow(Employee $employee): array
    {
        $positions = $employee->positions
            ->filter(fn (EmployeePosition $ep): bool => $ep->position !== null)
            ->sort(function (EmployeePosition $a, EmployeePosition $b): int {
                if ($a->is_primary !== $b->is_primary) {
                    return $b->is_primary <=> $a->is_primary;
                }

                return strcmp($a->position->title, $b->position->title);
            })
            ->values()
            ->map(fn (EmployeePosition $ep): array => [
                'id' => (int) $ep->position->id,
                'code' => (string) $ep->position->code,
                'title' => (string) $ep->position->title,
                'is_primary' => (bool) $ep->is_primary,
            ])
            ->all();

        $units = $employee->assignments
            ->map(function (EmployeeAssignment $assignment): ?array {
                if ($assignment->organizational_unit_id !== null && $assignment->organizationalUnit !== null) {
                    $u = $assignment->organizationalUnit;

                    $unitType = $u->unitType;

                    return [
                        'name' => (string) $u->name,
                        'unit_type' => $unitType !== null
                            ? (string) $unitType->name
                            : 'Unit',
                        'code' => filled($u->code) ? (string) $u->code : null,
                        'is_primary' => (bool) $assignment->is_primary,
                        'unit_type_color' => $unitType !== null && filled($unitType->color)
                            ? (string) $unitType->color
                            : null,
                    ];
                }

                if ($assignment->organization_id !== null && $assignment->organization !== null) {
                    return [
                        'name' => 'Organization: '.(string) $assignment->organization->name,
                        'unit_type' => 'Organization',
                        'code' => filled($assignment->organization->code)
                            ? (string) $assignment->organization->code
                            : null,
                        'is_primary' => (bool) $assignment->is_primary,
                        'unit_type_color' => null,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();

        $template = $employee->workScheduleTemplate;
        $schedulePayload = null;
        if ($template !== null && $template->id !== null) {
            $schedulePayload = [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'is_active' => (bool) $template->is_active,
            ];
        }

        return [
            'id' => (int) $employee->id,
            'display_name' => $this->formatEmployeeDisplayName($employee),
            'id_number' => (string) $employee->id_number,
            'attendance_id' => filled($employee->attendance_id) ? (string) $employee->attendance_id : null,
            'avatar_url' => $this->resolveAvatarUrl($employee->user),
            'units' => $units,
            'work_schedule' => $schedulePayload,
            'is_org_wide' => $employee->affiliations->isNotEmpty(),
            'positions' => $positions,
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

    private function resolveAvatarUrl(?User $user): ?string
    {
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
