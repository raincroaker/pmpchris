<?php

namespace App\Services;

use App\Models\OrganizationalUnit;
use Illuminate\Support\Collection;

class HrIndexUnitFilterCatalog
{
    /**
     * Unit ids allowed for index filters (whole org, or branch subtree when branch root is set).
     *
     * @return list<int>
     */
    public function allowedUnitIds(int $organizationId, ?int $branchRootId): array
    {
        if ($branchRootId === null) {
            return OrganizationalUnit::query()
                ->where('organization_id', $organizationId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return $this->collectBranchUnitSubtreeIds($organizationId, $branchRootId);
    }

    /**
     * @return list<int>
     */
    public function collectBranchUnitSubtreeIds(int $organizationId, int $branchRootId): array
    {
        $ids = [$branchRootId];
        $frontier = [$branchRootId];

        while ($frontier !== []) {
            $children = OrganizationalUnit::query()
                ->where('organization_id', $organizationId)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($children === []) {
                break;
            }

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $frontier)));
        }

        return $ids;
    }

    /**
     * @param  Collection<int, OrganizationalUnit>  $units
     * @return Collection<int, OrganizationalUnit>
     */
    public function orderUnitsForFilter(Collection $units, ?int $branchRootId): Collection
    {
        $byParent = $units
            ->groupBy(fn (OrganizationalUnit $unit): string => (string) ($unit->parent_id ?? 'root'));
        $ordered = collect();
        $seen = [];

        $walk = function (int $id) use (&$walk, $byParent, $units, &$ordered, &$seen): void {
            if (isset($seen[$id])) {
                return;
            }
            $unit = $units->firstWhere('id', $id);
            if ($unit === null) {
                return;
            }

            $seen[$id] = true;
            $ordered->push($unit);

            foreach ($byParent->get((string) $id, collect()) as $child) {
                $walk((int) $child->id);
            }
        };

        if ($branchRootId !== null) {
            $walk($branchRootId);

            return $ordered;
        }

        foreach ($byParent->get('root', collect()) as $root) {
            $walk((int) $root->id);
        }

        foreach ($units as $unit) {
            $walk((int) $unit->id);
        }

        return $ordered;
    }

    /**
     * @return list<array{id: int, code: string, name: string, unit_type: string}>
     */
    public function unitFilterOptionsForOrganization(int $organizationId, ?int $branchRootId): array
    {
        $allowedIds = $this->allowedUnitIds($organizationId, $branchRootId);
        if ($allowedIds === []) {
            return [];
        }

        $units = OrganizationalUnit::query()
            ->whereIn('id', $allowedIds)
            ->with('unitType:id,name')
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id', 'unit_type_id']);

        return $this->orderUnitsForFilter($units, $branchRootId)
            ->map(fn (OrganizationalUnit $unit): array => [
                'id' => (int) $unit->id,
                'code' => (string) $unit->code,
                'name' => (string) $unit->name,
                'unit_type' => $unit->unitType !== null
                    ? (string) $unit->unitType->name
                    : 'Unit',
            ])
            ->all();
    }
}
