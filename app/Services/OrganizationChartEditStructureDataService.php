<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use App\Models\UnitTypeParent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\User;

class OrganizationChartEditStructureDataService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * @return array{
     *     organization: array{id: int, code: string, name: string, is_active: bool}|null,
     *     areas: list<array{id: int, code: string, name: string, is_active: bool}>,
     *     unitTypes: LengthAwarePaginator,
     *     parentTypeOptions: list<array{id: int, name: string}>,
     *     rootUnitFilterOptions: list<array{value: string, label: string, code: string|null}>,
     *     filters: array{search: string, sort: string, direction: string, per_page: int, root_unit_filter: int|null}
     * }
     */
    public function build(
        string $search,
        string $sort,
        string $direction,
        int $perPage,
        ?int $rootUnitFilter = null,
        ?User $user = null,
    ): array {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            return [
                'organization' => null,
                'areas' => [],
                'unitTypes' => UnitType::query()->whereRaw('1 = 0')->paginate($perPage),
                'parentTypeOptions' => [],
                'rootUnitFilterOptions' => [],
                'filters' => [
                    'search' => $search,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                    'root_unit_filter' => null,
                ],
            ];
        }

        $rootUnitFilterOptions = $this->buildRootUnitFilterOptions($user);
        $allowedRootUnitIds = array_values(
            array_map(
                static fn (array $row): int => (int) $row['value'],
                array_filter(
                    $rootUnitFilterOptions,
                    static fn (array $row): bool => $row['value'] !== 'all'
                ),
            ),
        );
        $selectedRootUnitId = in_array($rootUnitFilter, $allowedRootUnitIds, true)
            ? $rootUnitFilter
            : null;
        $selectedSubtreeIds = $selectedRootUnitId !== null
            ? $this->collectSubtreeUnitIds((int) $organization->id, $selectedRootUnitId)
            : null;

        $unitTypes = UnitType::query()
            ->with([
                'allowedParentLinks.parentUnitType:id,name',
                'organizationalUnits' => fn ($query) => $query
                    ->where('organization_id', $organization->id)
                    ->when(
                        $selectedSubtreeIds !== null,
                        fn ($subQuery) => $subQuery->whereIn('id', $selectedSubtreeIds),
                    )
                    ->with('parent:id,name,code')
                    ->orderBy('name'),
            ])
            ->withCount([
                'organizationalUnits as units_count' => fn ($query) => $query
                    ->where('organization_id', $organization->id)
                    ->when(
                        $selectedSubtreeIds !== null,
                        fn ($subQuery) => $subQuery->whereIn('id', $selectedSubtreeIds),
                    ),
                'organizationalUnits as units_total_count',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (UnitType $unitType): array {
                return [
                    'id' => (int) $unitType->id,
                    'name' => (string) $unitType->name,
                    'color' => $unitType->color,
                    'can_be_root' => (bool) $unitType->can_be_root,
                    'description' => $unitType->description,
                    'is_active' => (bool) $unitType->is_active,
                    'units_count' => (int) $unitType->units_count,
                    'units_total_count' => (int) $unitType->units_total_count,
                    'units' => $unitType->organizationalUnits
                        ->map(fn (OrganizationalUnit $unit): array => [
                            'id' => (int) $unit->id,
                            'code' => (string) $unit->code,
                            'name' => (string) $unit->name,
                            'parent_name' => $unit->parent?->name,
                            'parent_code' => $unit->parent?->code,
                            'is_active' => (bool) $unit->is_active,
                        ])
                        ->values()
                        ->all(),
                    'allowed_parent_type_ids' => $unitType->allowedParentLinks
                        ->where('is_active', true)
                        ->pluck('parent_unit_type_id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all(),
                    'allowed_parent_type_names' => $unitType->allowedParentLinks
                        ->where('is_active', true)
                        ->map(fn (UnitTypeParent $link): ?string => $link->parentUnitType?->name)
                        ->filter(fn (?string $name): bool => is_string($name) && $name !== '')
                        ->values()
                        ->all(),
                ];
            });

        $parentTypeOptions = UnitType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (UnitType $unitType): array => [
                'id' => (int) $unitType->id,
                'name' => (string) $unitType->name,
            ])
            ->all();

        $areas = Area::query()
            ->where('organization_id', $organization->id)
            ->orderByRaw('LOWER(code)')
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'code', 'name', 'is_active'])
            ->map(fn (Area $area): array => [
                'id' => (int) $area->id,
                'code' => (string) ($area->code ?? ''),
                'name' => (string) $area->name,
                'is_active' => (bool) $area->is_active,
            ])
            ->values()
            ->all();

        return [
            'organization' => [
                'id' => (int) $organization->id,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
                'is_active' => (bool) $organization->is_active,
            ],
            'areas' => $areas,
            'unitTypes' => $unitTypes,
            'parentTypeOptions' => $parentTypeOptions,
            'rootUnitFilterOptions' => $rootUnitFilterOptions,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'root_unit_filter' => $selectedRootUnitId,
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string, code: string|null}>
     */
    private function buildRootUnitFilterOptions(?User $user): array
    {
        $roots = $this->branchContextService
            ->branchesForPicker($user)
            ->values()
            ->all();

        $options = [
            ['value' => 'all', 'label' => 'All units', 'code' => null],
        ];

        foreach ($roots as $root) {
            $options[] = [
                'value' => (string) $root['id'],
                'label' => (string) $root['name'],
                'code' => filled($root['code']) ? (string) $root['code'] : null,
            ];
        }

        return $options;
    }

    /**
     * @return list<int>
     */
    private function collectSubtreeUnitIds(int $organizationId, int $rootUnitId): array
    {
        $ids = [$rootUnitId];
        $frontier = [$rootUnitId];

        while ($frontier !== []) {
            $children = OrganizationalUnit::query()
                ->where('organization_id', $organizationId)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($children === []) {
                break;
            }

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $frontier)));
        }

        return $ids;
    }
}
