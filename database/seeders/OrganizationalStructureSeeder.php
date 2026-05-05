<?php

namespace Database\Seeders;

use App\Models\UnitType;
use App\Models\UnitTypeParent;
use Illuminate\Database\Seeder;

class OrganizationalStructureSeeder extends Seeder
{
    public function run(): void
    {
        $branch = UnitType::query()->updateOrCreate(
            ['name' => 'Branch'],
            [
                'color' => '#6366f1',
                'can_be_root' => true,
                'description' => 'Regional branch',
                'is_active' => true,
            ],
        );

        $department = UnitType::query()->updateOrCreate(
            ['name' => 'Department'],
            [
                'color' => '#10b981',
                'can_be_root' => false,
                'description' => 'Functional department',
                'is_active' => true,
            ],
        );

        $section = UnitType::query()->updateOrCreate(
            ['name' => 'Section'],
            [
                'color' => '#f59e0b',
                'can_be_root' => false,
                'description' => 'Sub-unit within a department',
                'is_active' => true,
            ],
        );

        $this->linkParentChild($branch->id, $department->id);
        $this->linkParentChild($department->id, $section->id);
        $this->linkParentChild($branch->id, $section->id);

        UnitType::query()->where('name', 'Head Office')->delete();
    }

    private function linkParentChild(int $parentUnitTypeId, int $childUnitTypeId): void
    {
        UnitTypeParent::query()->firstOrCreate(
            [
                'parent_unit_type_id' => $parentUnitTypeId,
                'child_unit_type_id' => $childUnitTypeId,
            ],
            ['is_active' => true],
        );
    }
}
