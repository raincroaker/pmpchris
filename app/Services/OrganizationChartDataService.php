<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrganizationChartDataService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private OrganizationChartActionAccessService $organizationChartActionAccessService,
    ) {}

    /**
     * @return array{
     *   orgChart: array{nodes: list<array<string, mixed>}>|null,
     *   chartScope: 'branch'|'all',
     *   chartUnitStatus: 'active'|'all',
     *   chartBranchId: int|null,
     *   chartBranches: list<array{id: int, code: string, name: string, area_name: string|null, group_label: string}>,
     *   chartAreas: list<array{id: int, code: string, name: string}>,
     *   chartCapabilities: array{canManageBranch: bool, canManageOrganizationNode: bool}
     * }
     */
    public function buildForRequest(Request $request): array
    {
        $organization = $this->branchContextService->defaultOrganization();

        $chartScope = $this->resolveChartScope($request);
        $chartUnitStatus = $this->resolveChartUnitStatus($request);

        if ($organization === null) {
            return [
                'orgChart' => null,
                'chartScope' => $chartScope,
                'chartUnitStatus' => $chartUnitStatus,
                'chartBranchId' => null,
                'chartBranches' => [],
                'chartAreas' => [],
                'chartCapabilities' => [
                    'canManageBranch' => false,
                    'canManageOrganizationNode' => false,
                ],
            ];
        }

        $root = $this->organizationChartActionAccessService->resolveChartRootForRequest($request);
        $chartBranches = $this->resolveChartBranches($request, $root);
        $chartAreas = $this->resolveChartAreas($organization);

        if ($chartScope === 'all') {
            $nodes = $this->buildOverallNodeSpecs($organization, $chartBranches, $chartUnitStatus);
            $chartCapabilities = $this->organizationChartActionAccessService->chartCapabilitiesForOverallChart($request->user());

            return [
                'orgChart' => $nodes === [] ? null : ['nodes' => $nodes],
                'chartScope' => 'all',
                'chartUnitStatus' => $chartUnitStatus,
                'chartBranchId' => null,
                'chartBranches' => $chartBranches,
                'chartAreas' => $chartAreas,
                'chartCapabilities' => $chartCapabilities,
            ];
        }

        $canManageBranch = $this->organizationChartActionAccessService->canManageChartBranch($request->user(), $root);
        $canManageOrganizationNode = $this->organizationChartActionAccessService->canManageOrganizationNode($request->user(), $root);

        if ($root === null) {
            return [
                'orgChart' => null,
                'chartScope' => 'branch',
                'chartUnitStatus' => $chartUnitStatus,
                'chartBranchId' => null,
                'chartBranches' => $chartBranches,
                'chartAreas' => $chartAreas,
                'chartCapabilities' => [
                    'canManageBranch' => false,
                    'canManageOrganizationNode' => false,
                ],
            ];
        }

        $root->loadMissing('unitType');

        $nodes = $this->buildNodeSpecs($root, $organization, $chartUnitStatus);

        if ($nodes === []) {
            return [
                'orgChart' => null,
                'chartScope' => 'branch',
                'chartUnitStatus' => $chartUnitStatus,
                'chartBranchId' => null,
                'chartBranches' => $chartBranches,
                'chartAreas' => $chartAreas,
                'chartCapabilities' => [
                    'canManageBranch' => $canManageBranch,
                    'canManageOrganizationNode' => $canManageOrganizationNode,
                ],
            ];
        }

        return [
            'orgChart' => ['nodes' => $nodes],
            'chartScope' => 'branch',
            'chartUnitStatus' => $chartUnitStatus,
            'chartBranchId' => (int) $root->id,
            'chartBranches' => $chartBranches,
            'chartAreas' => $chartAreas,
            'chartCapabilities' => [
                'canManageBranch' => $canManageBranch,
                'canManageOrganizationNode' => $canManageOrganizationNode,
            ],
        ];
    }

    private function resolveChartScope(Request $request): string
    {
        $scope = strtolower(trim((string) $request->query('chart_scope', 'branch')));

        return $scope === 'all' ? 'all' : 'branch';
    }

    private function resolveChartUnitStatus(Request $request): string
    {
        $status = strtolower(trim((string) $request->query('chart_unit_status', 'active')));

        return $status === 'all' ? 'all' : 'active';
    }

    /**
     * @return list<array{id: int, code: string, name: string, area_name: string|null, group_label: string}>
     */
    private function resolveChartBranches(Request $request, ?OrganizationalUnit $resolvedRoot): array
    {
        $allChartBranches = $this->branchContextService->branchesForPicker()->values()->all();
        if ($allChartBranches !== []) {
            return $allChartBranches;
        }

        if (! $resolvedRoot instanceof OrganizationalUnit) {
            return [];
        }

        return [[
            'id' => (int) $resolvedRoot->id,
            'code' => (string) $resolvedRoot->code,
            'name' => (string) $resolvedRoot->name,
            'area_name' => null,
            'group_label' => 'Branches',
        ]];
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function resolveChartAreas(Organization $organization): array
    {
        return Area::query()
            ->where('organization_id', $organization->id)
            ->orderByRaw('LOWER(code)')
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'code', 'name'])
            ->map(fn (Area $area): array => [
                'id' => (int) $area->id,
                'code' => (string) ($area->code ?? ''),
                'name' => (string) $area->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildNodeSpecs(OrganizationalUnit $root, Organization $organization, string $chartUnitStatus): array
    {
        $unitsQuery = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->with('unitType');

        if ($chartUnitStatus === 'active') {
            $unitsQuery->where('is_active', true);
        }

        $allUnits = $unitsQuery->get();

        $reachableIds = $this->reachableIdsFromRoot($root->id, $allUnits);

        if (! $reachableIds->has($root->id)) {
            return [];
        }

        /** @var Collection<int|string, Collection<int, OrganizationalUnit>> $byParent */
        $byParent = $allUnits
            ->filter(fn (OrganizationalUnit $u): bool => $reachableIds->has($u->id))
            ->groupBy(fn (OrganizationalUnit $u): int|string => $u->parent_id ?? 0);

        /** @var list<int> $reachableUnitIds */
        $reachableUnitIds = $allUnits
            ->filter(fn (OrganizationalUnit $u): bool => $reachableIds->has($u->id))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $employeesByUnitId = $this->employeesPayloadIndexedByUnitId($reachableUnitIds);
        $organizationEmployees = $this->organizationEmployeesPayload($organization->id);

        $orgVueId = 'organization-'.$organization->id;

        $specs = [
            [
                'id' => $orgVueId,
                'type' => 'org',
                'parentId' => null,
                'siblingIndex' => 0,
                'data' => [
                    'fullName' => $organization->name,
                    'alias' => is_string($organization->code) ? $organization->code : '',
                    'employees' => $organizationEmployees,
                ],
            ],
        ];

        $this->appendNodeAndDescendants($root, $orgVueId, 0, $byParent, $specs, $employeesByUnitId);

        return $specs;
    }

    /**
     * @param  list<array{id: int, code: string, name: string, area_name: string|null, group_label: string}>  $chartBranches
     * @return list<array<string, mixed>>
     */
    private function buildOverallNodeSpecs(Organization $organization, array $chartBranches, string $chartUnitStatus): array
    {
        $unitsQuery = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->with('unitType');

        if ($chartUnitStatus === 'active') {
            $unitsQuery->where('is_active', true);
        }

        $allUnits = $unitsQuery->get();

        if ($allUnits->isEmpty()) {
            return [];
        }

        /** @var list<int> $rootIds */
        $rootIds = collect($chartBranches)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($rootIds === []) {
            return [];
        }

        $rootIdSet = array_flip($rootIds);

        /** @var Collection<int|string, Collection<int, OrganizationalUnit>> $byParent */
        $byParent = $allUnits->groupBy(fn (OrganizationalUnit $u): int|string => $u->parent_id ?? 0);

        /** @var list<int> $reachableUnitIds */
        $reachableUnitIds = $allUnits
            ->filter(fn (OrganizationalUnit $u): bool => isset($rootIdSet[(int) $u->id]))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($rootIds as $rootId) {
            $reachable = $this->reachableIdsFromRoot($rootId, $allUnits);
            foreach ($reachable->keys() as $id) {
                $idInt = (int) $id;
                if (! in_array($idInt, $reachableUnitIds, true)) {
                    $reachableUnitIds[] = $idInt;
                }
            }
        }

        if ($reachableUnitIds === []) {
            return [];
        }

        $employeesByUnitId = $this->employeesPayloadIndexedByUnitId($reachableUnitIds);
        $organizationEmployees = $this->organizationEmployeesPayload($organization->id);
        $orgVueId = 'organization-'.$organization->id;

        $specs = [[
            'id' => $orgVueId,
            'type' => 'org',
            'parentId' => null,
            'siblingIndex' => 0,
            'data' => [
                'fullName' => $organization->name,
                'alias' => is_string($organization->code) ? $organization->code : '',
                'employees' => $organizationEmployees,
            ],
        ]];

        $rootUnits = $allUnits
            ->filter(fn (OrganizationalUnit $u): bool => isset($rootIdSet[(int) $u->id]))
            ->sortBy(fn (OrganizationalUnit $u): string => (string) $u->name)
            ->values();

        foreach ($rootUnits as $index => $rootUnit) {
            $this->appendNodeAndDescendants(
                $rootUnit,
                $orgVueId,
                $index,
                $byParent,
                $specs,
                $employeesByUnitId,
            );
        }

        return $specs;
    }

    /**
     * @param  Collection<int, OrganizationalUnit>  $allUnits
     * @return Collection<int, bool>
     */
    private function reachableIdsFromRoot(int $rootId, Collection $allUnits): Collection
    {
        /** @var Collection<int|string, Collection<int, OrganizationalUnit>> $byParentId */
        $byParentId = $allUnits->groupBy(fn (OrganizationalUnit $u): int|string => $u->parent_id ?? 0);

        $reachable = collect();
        $queue = [$rootId];

        while ($queue !== []) {
            $id = array_shift($queue);
            if ($reachable->has($id)) {
                continue;
            }
            $reachable->put($id, true);

            foreach ($byParentId->get($id, collect()) as $child) {
                $queue[] = (int) $child->id;
            }
        }

        return $reachable;
    }

    /**
     * @param  Collection<int|string, Collection<int, OrganizationalUnit>>  $byParent
     * @param  list<array<string, mixed>>  $specs
     * @param  array<int, list<array{assignment_id: int, employee_id: int, full_name: string, position_title: string|null, position_options: list<array{id: int, title: string}>, avatar_url: string|null, is_primary: bool, is_head: bool}>>  $employeesByUnitId
     */
    private function appendNodeAndDescendants(
        OrganizationalUnit $unit,
        ?string $parentVueId,
        int $siblingIndex,
        Collection $byParent,
        array &$specs,
        array $employeesByUnitId,
    ): void {
        $vueId = 'unit-'.$unit->id;

        $typeName = $unit->unitType?->name;
        $vueType = $this->mapUnitTypeToVueNodeType(is_string($typeName) ? $typeName : '');

        $specs[] = [
            'id' => $vueId,
            'type' => $vueType,
            'parentId' => $parentVueId,
            'siblingIndex' => $siblingIndex,
            'data' => [
                'fullName' => $unit->name,
                'alias' => is_string($unit->code) ? $unit->code : '',
                'isActive' => (bool) $unit->is_active,
                'unitTypeColor' => is_string($unit->unitType?->color) ? strtolower(trim($unit->unitType->color)) : null,
                'employees' => $employeesByUnitId[$unit->id] ?? [],
            ],
        ];

        $children = $byParent->get($unit->id, collect());
        $sorted = $children->sortBy(fn (OrganizationalUnit $c): string => (string) $c->name)->values();

        foreach ($sorted as $index => $child) {
            $this->appendNodeAndDescendants(
                $child,
                $vueId,
                $index,
                $byParent,
                $specs,
                $employeesByUnitId,
            );
        }
    }

    /**
     * Active assignments only (end_date null). Position title from assignment_positions → employee_position → position.
     * Duplicate employee_id per unit: first row wins after sorting by display name (dropped row's is_head is not merged).
     *
     * Loads only rows with a non-null organizational_unit_id in the given unit id list. Assignments that use only
     * organization_id (org-level, no unit) are intentionally excluded here until the chart merges them under the org node.
     *
     * @param  list<int>  $unitIds
     * @return array<int, list<array{assignment_id: int, employee_id: int, full_name: string, position_title: string|null, position_options: list<array{id: int, title: string}>, avatar_url: string|null, is_primary: bool, is_head: bool}>>
     */
    private function employeesPayloadIndexedByUnitId(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }
        $today = now()->toDateString();

        /** @var array<int, list<array{assignment_id: int, employee_id: int, full_name: string, position_title: string|null, position_options: list<array{id: int, title: string}>, avatar_url: string|null, is_primary: bool, is_head: bool}>> $byUnit */
        $byUnit = [];
        foreach ($unitIds as $id) {
            $byUnit[(int) $id] = [];
        }

        $assignments = EmployeeAssignment::query()
            ->whereIn('organizational_unit_id', $unitIds)
            ->whereNull('end_date')
            ->whereHas('employee.currentEmployment', function ($employmentQuery) use ($today): void {
                $employmentQuery->whereNull('deleted_at')
                    ->where('employment_status', EmployeeEmployment::STATUS_ACTIVE)
                    ->where(function ($dateScope) use ($today): void {
                        $dateScope->whereNull('separation_date')
                            ->orWhereDate('separation_date', '>=', $today);
                    });
            })
            ->with([
                'employee.user:id,employee_id,avatar_path',
                'employee.positions' => function ($query): void {
                    $today = now()->toDateString();
                    $query->whereNull('deleted_at')
                        ->where(function ($dateScope) use ($today): void {
                            $dateScope->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        })
                        ->with('position:id,title')
                        ->orderByDesc('is_primary')
                        ->orderByDesc('start_date')
                        ->orderByDesc('id');
                },
                'assignmentPositions.employeePosition.position',
            ])
            ->get();

        foreach ($assignments as $assignment) {
            $unitId = (int) $assignment->organizational_unit_id;
            if (! array_key_exists($unitId, $byUnit)) {
                continue;
            }

            $employee = $assignment->employee;
            if (! $employee instanceof Employee) {
                continue;
            }

            $byUnit[$unitId][] = [
                'assignment_id' => (int) $assignment->id,
                'employee_id' => (int) $assignment->employee_id,
                'full_name' => $this->formatEmployeeDisplayName($employee),
                'position_title' => $this->resolveAssignmentLinkedPositionTitle($assignment),
                'position_options' => $this->resolveEmployeePositionOptions($employee),
                'avatar_url' => $this->resolveAvatarUrl($employee),
                'is_primary' => (bool) $assignment->is_primary,
                'is_head' => (bool) $assignment->is_head,
            ];
        }

        foreach ($byUnit as $uid => $rows) {
            usort(
                $rows,
                fn (array $a, array $b): int => [$a['full_name'], $a['employee_id']] <=> [$b['full_name'], $b['employee_id']],
            );
            $seen = [];
            $deduped = [];
            foreach ($rows as $row) {
                $eid = $row['employee_id'];
                if (isset($seen[$eid])) {
                    continue;
                }
                $seen[$eid] = true;
                $deduped[] = $row;
            }
            $byUnit[$uid] = $deduped;
        }

        return $byUnit;
    }

    /**
     * Active org-level assignments only (organization_id set, organizational_unit_id null, end_date null).
     *
     * @return list<array{assignment_id: int, employee_id: int, full_name: string, position_title: string|null, position_options: list<array{id: int, title: string}>, avatar_url: string|null, is_primary: bool, is_head: bool}>
     */
    private function organizationEmployeesPayload(int $organizationId): array
    {
        $today = now()->toDateString();

        $assignments = EmployeeAssignment::query()
            ->where('organization_id', $organizationId)
            ->whereNull('organizational_unit_id')
            ->whereNull('end_date')
            ->whereHas('employee.currentEmployment', function ($employmentQuery) use ($today): void {
                $employmentQuery->whereNull('deleted_at')
                    ->where('employment_status', EmployeeEmployment::STATUS_ACTIVE)
                    ->where(function ($dateScope) use ($today): void {
                        $dateScope->whereNull('separation_date')
                            ->orWhereDate('separation_date', '>=', $today);
                    });
            })
            ->with([
                'employee.user:id,employee_id,avatar_path',
                'employee.positions' => function ($query): void {
                    $today = now()->toDateString();
                    $query->whereNull('deleted_at')
                        ->where(function ($dateScope) use ($today): void {
                            $dateScope->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        })
                        ->with('position:id,title')
                        ->orderByDesc('is_primary')
                        ->orderByDesc('start_date')
                        ->orderByDesc('id');
                },
                'assignmentPositions.employeePosition.position',
            ])
            ->get();

        $rows = [];
        foreach ($assignments as $assignment) {
            $employee = $assignment->employee;
            if (! $employee instanceof Employee) {
                continue;
            }

            $rows[] = [
                'assignment_id' => (int) $assignment->id,
                'employee_id' => (int) $assignment->employee_id,
                'full_name' => $this->formatEmployeeDisplayName($employee),
                'position_title' => $this->resolveAssignmentLinkedPositionTitle($assignment),
                'position_options' => $this->resolveEmployeePositionOptions($employee),
                'avatar_url' => $this->resolveAvatarUrl($employee),
                'is_primary' => (bool) $assignment->is_primary,
                'is_head' => (bool) $assignment->is_head,
            ];
        }

        usort(
            $rows,
            fn (array $a, array $b): int => [$a['full_name'], $a['employee_id']] <=> [$b['full_name'], $b['employee_id']],
        );

        $seen = [];
        $deduped = [];
        foreach ($rows as $row) {
            $employeeId = $row['employee_id'];
            if (isset($seen[$employeeId])) {
                continue;
            }

            $seen[$employeeId] = true;
            $deduped[] = $row;
        }

        return $deduped;
    }

    private function formatEmployeeDisplayName(Employee $employee): string
    {
        $parts = array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->suffix,
        ], fn ($p): bool => is_string($p) && $p !== '');

        return implode(' ', $parts);
    }

    private function resolveAssignmentLinkedPositionTitle(EmployeeAssignment $assignment): ?string
    {
        $today = now()->toDateString();
        $links = $assignment->assignmentPositions->filter(function ($ap): bool {
            return $ap->employeePosition !== null
                && $ap->employeePosition->position !== null;
        })->filter(function ($ap) use ($today): bool {
            if ($ap->deleted_at !== null) {
                return false;
            }
            if ($ap->end_date === null) {
                return true;
            }

            return $ap->end_date->toDateString() >= $today;
        });

        if ($links->isEmpty()) {
            return null;
        }

        $primary = $links->firstWhere('is_primary_for_assignment', true);
        $chosen = $primary ?? $links->sort(function ($a, $b): int {
            $da = $a->start_date?->getTimestamp() ?? 0;
            $db = $b->start_date?->getTimestamp() ?? 0;
            if ($da !== $db) {
                return $db <=> $da;
            }

            return $b->id <=> $a->id;
        })->first();

        $title = $chosen?->employeePosition?->position?->title;

        return is_string($title) && $title !== '' ? $title : null;
    }

    private function resolveAvatarUrl(Employee $employee): ?string
    {
        $user = $employee->user;
        if (! $user instanceof User) {
            return null;
        }

        $avatarPath = $user->avatar_path;
        if (! is_string($avatarPath) || $avatarPath === '') {
            return null;
        }

        return asset('storage/'.$avatarPath);
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function resolveEmployeePositionOptions(Employee $employee): array
    {
        /** @var Collection<int, \App\Models\EmployeePosition> $positions */
        $positions = $employee->positions;

        return $positions
            ->map(function ($employeePosition): ?array {
                $position = $employeePosition->position;
                $title = is_string($position?->title) ? trim((string) $position->title) : '';
                if ($position === null || $title === '') {
                    return null;
                }

                return [
                    'id' => (int) $position->id,
                    'title' => $title,
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null)
            ->unique('id')
            ->values()
            ->all();
    }

    private function mapUnitTypeToVueNodeType(string $unitTypeName): string
    {
        return match ($unitTypeName) {
            'Branch' => 'branch',
            'Department' => 'department',
            'Section' => 'section',
            default => 'node-generic',
        };
    }
}
