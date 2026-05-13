<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\UnitType;
use Illuminate\Database\Seeder;

class DemoCooperativeSeeder extends Seeder
{
    /**
     * Local demo org: Panabo Multipurpose Cooperative (PMPC).
     * Lean branch footprint: two roots (PAN, TAG) under Davao del Norte.
     * PAN has Human Resources (Interns, Talent Acquisition); TAG has HR (Employee Relations).
     * Position catalog is HR Admin + Intern (titles for future hires only; no intern employees in dev seed).
     */
    public function run(): void
    {
        $organization = $this->ensureOrganization();

        $areaDefinitions = [
            ['code' => 'AREA-DAVNOR', 'name' => 'Davao del Norte'],
        ];

        $areasByCode = [];
        foreach ($areaDefinitions as $row) {
            $areasByCode[$row['code']] = $this->ensureArea($organization, $row['code'], $row['name']);
        }

        $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
        $departmentType = UnitType::query()->where('name', 'Department')->firstOrFail();
        $sectionType = UnitType::query()->where('name', 'Section')->firstOrFail();

        $branchDefinitions = [
            ['code' => 'PAN', 'name' => 'Panabo Branch', 'area_code' => 'AREA-DAVNOR'],
            ['code' => 'TAG', 'name' => 'Tagum Branch', 'area_code' => 'AREA-DAVNOR'],
        ];

        $branchRootsByCode = [];
        foreach ($branchDefinitions as $def) {
            $area = $areasByCode[$def['area_code']];
            $branchRootsByCode[$def['code']] = $this->ensureRootUnit(
                $organization,
                $def['code'],
                $def['name'],
                $branchType->id,
                $area->id,
            );
        }

        $this->seedDepartmentsSectionsAndPositions(
            $organization,
            $branchRootsByCode['PAN'],
            $departmentType->id,
            $sectionType->id,
            'PAN',
            ['Human Resources'],
            [
                'Human Resources' => ['Interns', 'Talent Acquisition'],
            ],
        );

        $this->seedDepartmentsSectionsAndPositions(
            $organization,
            $branchRootsByCode['TAG'],
            $departmentType->id,
            $sectionType->id,
            'TAG',
            ['Human Resources'],
            [
                'Human Resources' => ['Employee Relations'],
            ],
        );

        $this->seedPositionCatalog($organization);
    }

    private function ensureOrganization(): Organization
    {
        return Organization::query()->firstOrCreate(
            ['code' => 'PMPC'],
            ['name' => 'Panabo Multipurpose Cooperative', 'is_active' => true],
        );
    }

    private function ensureArea(Organization $organization, string $code, string $name): Area
    {
        return Area::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'organization_id' => $organization->id,
                'is_active' => true,
            ],
        );
    }

    private function ensureRootUnit(
        Organization $organization,
        string $code,
        string $name,
        int $unitTypeId,
        ?int $areaId,
    ): OrganizationalUnit {
        return OrganizationalUnit::query()->firstOrCreate(
            ['code' => $code, 'organization_id' => $organization->id],
            [
                'name' => $name,
                'unit_type_id' => $unitTypeId,
                'parent_id' => null,
                'area_id' => $areaId,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  list<string>  $departmentNames
     * @param  array<string, list<string>>  $sectionNamesByDepartment
     */
    private function seedDepartmentsSectionsAndPositions(
        Organization $organization,
        OrganizationalUnit $root,
        int $departmentTypeId,
        int $sectionTypeId,
        string $unitCodePrefix,
        array $departmentNames,
        array $sectionNamesByDepartment,
    ): void {
        foreach ($departmentNames as $index => $departmentName) {
            $deptNumber = $index + 1;
            $deptCode = "{$unitCodePrefix}-D{$deptNumber}";

            $department = OrganizationalUnit::query()->updateOrCreate(
                ['code' => $deptCode, 'organization_id' => $organization->id],
                [
                    'name' => $departmentName,
                    'unit_type_id' => $departmentTypeId,
                    'parent_id' => $root->id,
                    'area_id' => null,
                    'is_active' => true,
                ],
            );

            $sectionNames = $sectionNamesByDepartment[$departmentName] ?? [];
            foreach ($sectionNames as $sectionIndex => $sectionName) {
                $sectionNumber = $sectionIndex + 1;
                $sectionCode = "{$unitCodePrefix}-D{$deptNumber}-S{$sectionNumber}";

                OrganizationalUnit::query()->updateOrCreate(
                    ['code' => $sectionCode, 'organization_id' => $organization->id],
                    [
                        'name' => $sectionName,
                        'unit_type_id' => $sectionTypeId,
                        'parent_id' => $department->id,
                        'area_id' => null,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function seedPositionCatalog(Organization $organization): void
    {
        $positionCatalog = [
            ['code' => 'HR-ADM', 'title' => 'HR Admin', 'description' => 'Human resources administration and records.'],
            ['code' => 'INTERN', 'title' => 'Intern', 'description' => 'Entry-level internship (catalog for future hires).'],
        ];

        foreach ($positionCatalog as $row) {
            Position::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => $row['code'],
                ],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
