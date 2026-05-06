<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\UnitType;
use App\Services\EmploymentHireAdjustmentBoundary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('latest hire max is earliest start across affiliation position and assignment rows', function (): void {
    $organization = Organization::factory()->create(['is_active' => true]);

    $unitType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);

    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2019-01-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
    ]);

    $catalogPosition = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    EmployeePosition::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $catalogPosition->id,
        'is_primary' => true,
        'start_date' => '2020-08-15',
        'end_date' => null,
        'notes' => null,
    ]);

    EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $root->id,
        'is_primary' => false,
        'is_head' => false,
        'start_date' => '2022-03-01',
        'end_date' => null,
    ]);

    expect(EmploymentHireAdjustmentBoundary::latestPermittedHireDateIsoForEmployment((int) $employment->getKey()))
        ->toBe('2020-06-01');
});

test('latest hire max is null when employment has no related spans', function (): void {
    $employment = EmployeeEmployment::factory()->create([
        'hire_date' => '2019-01-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    expect(EmploymentHireAdjustmentBoundary::latestPermittedHireDateIsoForEmployment((int) $employment->getKey()))
        ->toBeNull();
});

test('boundary batch map aggregates employments independently', function (): void {
    $organization = Organization::factory()->create(['is_active' => true]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();

    $employmentWithSpan = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2019-01-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    EmployeePosition::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employmentWithSpan->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2021-05-05',
        'end_date' => null,
        'notes' => null,
    ]);

    $employmentBare = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2018-01-01',
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'is_current' => false,
        'separation_date' => '2018-06-01',
    ]);

    $mapped = EmploymentHireAdjustmentBoundary::latestPermittedHireDatesByEmploymentIds([
        (int) $employmentWithSpan->getKey(),
        (int) $employmentBare->getKey(),
    ]);

    expect($mapped[(int) $employmentWithSpan->getKey()])->toBe('2021-05-05')
        ->and($mapped[(int) $employmentBare->getKey()])->toBeNull();
});
