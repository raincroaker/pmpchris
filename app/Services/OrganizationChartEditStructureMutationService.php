<?php

namespace App\Services;

use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use App\Models\UnitTypeParent;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationChartEditStructureMutationService
{
    /**
     * Hex palette allowed for structure editor — aligned with the Edit Structure Vue color picker.
     *
     * @return list<string>
     */
    public static function allowedUnitTypePaletteHexNormalized(): array
    {
        return [
            '#6366f1',
            '#10b981',
            '#f59e0b',
            '#f43f5e',
            '#d946ef',
            '#65a30d',
        ];
    }

    /**
     * @param  list<int>  $parentTypeIds
     */
    public function createUnitType(
        string $name,
        string $description,
        string $normalizedColorHex,
        bool $canBeRoot,
        array $parentTypeIds,
    ): UnitType {
        $this->guardColorPalette($normalizedColorHex);
        $this->guardRootsAndParents($canBeRoot, $parentTypeIds);

        /** @var UnitType $created */
        $created = DB::transaction(function () use ($name, $description, $normalizedColorHex, $canBeRoot, $parentTypeIds): UnitType {
            $unitType = UnitType::query()->create([
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'color' => $normalizedColorHex,
                'can_be_root' => $canBeRoot,
                'is_active' => true,
            ]);

            $this->syncParentLinksIgnoringSelf($unitType->id, $parentTypeIds, $canBeRoot);

            return $unitType->fresh(['allowedParentLinks']);
        });

        return $created;
    }

    /**
     * @param  list<int>  $parentTypeIds
     */
    public function updateUnitType(
        UnitType $unitType,
        string $name,
        string $description,
        string $normalizedColorHex,
        bool $canBeRoot,
        array $parentTypeIds,
    ): UnitType {
        $this->guardColorPalette($normalizedColorHex);
        $this->guardRootsAndParents($canBeRoot, $parentTypeIds, $unitType->id);

        DB::transaction(function () use ($unitType, $name, $description, $normalizedColorHex, $canBeRoot, $parentTypeIds): void {
            $unitType->fill([
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'color' => $normalizedColorHex,
                'can_be_root' => $canBeRoot,
            ]);
            $unitType->save();

            $this->syncParentLinksIgnoringSelf($unitType->id, $parentTypeIds, $canBeRoot);
        });

        return $unitType->fresh(['allowedParentLinks']);
    }

    public function deactivateUnitType(UnitType $unitType): void
    {
        $hasUnits = OrganizationalUnit::query()->where('unit_type_id', $unitType->id)->exists();
        if ($hasUnits) {
            throw ValidationException::withMessages([
                'unit_type' => 'Cannot deactivate a unit type that is still referenced by organizational units.',
            ]);
        }

        DB::transaction(function () use ($unitType): void {
            UnitTypeParent::query()
                ->where(function (Builder $builder) use ($unitType): void {
                    $builder
                        ->where('parent_unit_type_id', $unitType->id)
                        ->orWhere('child_unit_type_id', $unitType->id);
                })
                ->update(['is_active' => false]);

            $unitType->is_active = false;
            $unitType->save();
        });
    }

    private function guardColorPalette(string $normalizedHex): void
    {
        $needle = strtolower($normalizedHex);
        $allowed = array_map(static fn (string $h): string => strtolower($h), self::allowedUnitTypePaletteHexNormalized());
        if (! in_array($needle, $allowed, true)) {
            throw ValidationException::withMessages([
                'color' => 'Pick a supported color.',
            ]);
        }
    }

    /**
     * @param  list<int>  $parentTypeIds
     */
    private function guardRootsAndParents(bool $canBeRoot, array $parentTypeIds, ?int $excludingChildId = null): void
    {
        $normalizedParentIds = $this->uniquePositiveIds($parentTypeIds);
        foreach ($normalizedParentIds as $pid) {
            if ($excludingChildId !== null && $pid === $excludingChildId) {
                throw ValidationException::withMessages([
                    'parent_type_ids' => 'A unit type cannot be its own parent.',
                ]);
            }

            /** @var UnitType|null $parent */
            $parent = UnitType::query()->find($pid);
            if (! $parent instanceof UnitType || ! $parent->is_active) {
                throw ValidationException::withMessages([
                    'parent_type_ids' => 'One or more parent types are invalid or inactive.',
                ]);
            }
        }

        if ($canBeRoot) {
            if ($normalizedParentIds !== []) {
                throw ValidationException::withMessages([
                    'can_be_root' => 'Root types cannot have allowed parent types.',
                ]);
            }

            return;
        }

        if ($normalizedParentIds === []) {
            throw ValidationException::withMessages([
                'parent_type_ids' => 'Select at least one parent type when this type is not root.',
            ]);
        }
    }

    /**
     * @param  list<int>  $parentTypeIds
     */
    private function syncParentLinksIgnoringSelf(int $childUnitTypeId, array $parentTypeIds, bool $canBeRoot): void
    {
        $ids = $this->uniquePositiveIds($parentTypeIds);
        foreach ($ids as $pid) {
            if ($pid === $childUnitTypeId) {
                throw ValidationException::withMessages([
                    'parent_type_ids' => 'A unit type cannot be its own parent.',
                ]);
            }
        }

        if ($canBeRoot) {
            UnitTypeParent::query()
                ->where('child_unit_type_id', $childUnitTypeId)
                ->update(['is_active' => false]);

            return;
        }

        foreach ($ids as $parentUnitTypeId) {
            UnitTypeParent::query()->updateOrCreate(
                [
                    'parent_unit_type_id' => $parentUnitTypeId,
                    'child_unit_type_id' => $childUnitTypeId,
                ],
                ['is_active' => true],
            );
        }

        UnitTypeParent::query()
            ->where('child_unit_type_id', $childUnitTypeId)
            ->whereNotIn('parent_unit_type_id', $ids)
            ->update(['is_active' => false]);
    }

    /**
     * @param  list<int|numeric-string>  $ids
     * @return list<int>
     */
    private function uniquePositiveIds(array $ids): array
    {
        /** @var list<int> $out */
        $out = [];

        foreach ($ids as $id) {
            $n = is_int($id) ? $id : (int) $id;
            if ($n > 0 && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        sort($out);

        return $out;
    }
}
