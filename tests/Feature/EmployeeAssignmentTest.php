<?php

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee assignment factory persists expected attributes', function () {
    $assignment = EmployeeAssignment::factory()->create([
        'start_date' => '2024-01-15',
        'end_date' => '2025-12-31',
        'is_primary' => true,
        'is_head' => true,
    ]);

    expect($assignment->exists)->toBeTrue()
        ->and(Carbon::parse((string) $assignment->start_date)->toDateString())->toBe('2024-01-15')
        ->and(Carbon::parse((string) $assignment->end_date)->toDateString())->toBe('2025-12-31')
        ->and($assignment->is_primary)->toBeTrue()
        ->and($assignment->is_head)->toBeTrue()
        ->and($assignment->employee_employment_id)->not->toBeNull()
        ->and((int) $assignment->employmentPeriod->employee_id)->toBe((int) $assignment->employee_id)
        ->and($assignment->organization_id)->toBeNull()
        ->and($assignment->organizational_unit_id)->not->toBeNull();
});

test('employee assignment factory default is unit-level XOR', function () {
    $assignment = EmployeeAssignment::factory()->create();

    expect($assignment->organization_id)->toBeNull()
        ->and($assignment->organizational_unit_id)->not->toBeNull();
});

test('employee assignment can be org-level without organizational unit', function () {
    $employee = Employee::factory()->create();
    $organization = Organization::factory()->create();

    $assignment = EmployeeAssignment::factory()->for($employee)->create([
        'organization_id' => $organization->id,
        'organizational_unit_id' => null,
        'start_date' => '2024-06-01',
        'end_date' => null,
        'is_primary' => true,
        'is_head' => false,
    ]);

    expect($assignment->organizational_unit_id)->toBeNull()
        ->and((int) $assignment->organization_id)->toBe((int) $organization->id)
        ->and($assignment->organization->is($organization))->toBeTrue();
});

test('employee assignment rejects both organization and unit null', function () {
    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->for($employee)->create();

    expect(fn () => EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => null,
        'is_primary' => false,
        'is_head' => false,
        'start_date' => '2024-01-01',
        'end_date' => null,
    ]))->toThrow(InvalidArgumentException::class);
});

test('employee assignment rejects both organization and unit set', function () {
    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->for($employee)->create();
    $organization = Organization::factory()->create();
    $unit = OrganizationalUnit::factory()->create(['organization_id' => $organization->id]);

    expect(fn () => EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'organizational_unit_id' => $unit->id,
        'is_primary' => false,
        'is_head' => false,
        'start_date' => '2024-01-01',
        'end_date' => null,
    ]))->toThrow(InvalidArgumentException::class);
});

test('employee assignment rejects employment period owned by another employee', function () {
    $employee = Employee::factory()->create();
    $otherEmployee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->for($otherEmployee)->create();

    expect(fn () => EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => OrganizationalUnit::factory()->create()->id,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => '2024-01-01',
        'end_date' => null,
    ]))->toThrow(InvalidArgumentException::class, 'employee_employment_id must belong to the same employee_id.');
});

test('employee has many assignments', function () {
    $employee = Employee::factory()->create();

    EmployeeAssignment::factory()->count(2)->for($employee)->create();

    expect($employee->assignments)->toHaveCount(2);
});

test('organizational unit has many employee assignments', function () {
    $unit = OrganizationalUnit::factory()->create();

    EmployeeAssignment::factory()->count(2)->create([
        'organizational_unit_id' => $unit->id,
        'organization_id' => null,
    ]);

    expect($unit->employeeAssignments)->toHaveCount(2);
});
