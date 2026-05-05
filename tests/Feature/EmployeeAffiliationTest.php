<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee affiliation factory persists expected attributes', function () {
    $affiliation = EmployeeAffiliation::factory()->create([
        'start_date' => '2023-06-01',
        'end_date' => null,
        'is_primary' => true,
    ]);

    expect($affiliation->exists)->toBeTrue()
        ->and(Carbon::parse((string) $affiliation->start_date)->toDateString())->toBe('2023-06-01')
        ->and($affiliation->end_date)->toBeNull()
        ->and($affiliation->is_primary)->toBeTrue()
        ->and($affiliation->employee_employment_id)->not->toBeNull()
        ->and((int) $affiliation->employmentPeriod->employee_id)->toBe((int) $affiliation->employee_id)
        ->and($affiliation->rootUnit->unitType->can_be_root)->toBeTrue()
        ->and((int) $affiliation->organization_id)->toBe((int) $affiliation->rootUnit->organization_id);
});

test('employee affiliation can be org-wide without root unit', function () {
    $employee = Employee::factory()->create();
    $organization = Organization::factory()->create();

    $affiliation = EmployeeAffiliation::factory()->for($employee)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'start_date' => '2022-01-01',
        'is_primary' => false,
    ]);

    expect($affiliation->root_unit_id)->toBeNull()
        ->and((int) $affiliation->organization_id)->toBe((int) $organization->id)
        ->and($affiliation->organization->is($organization))->toBeTrue();
});

test('employee has many affiliations', function () {
    $employee = Employee::factory()->create();

    EmployeeAffiliation::factory()->count(2)->for($employee)->create();

    expect($employee->affiliations)->toHaveCount(2);
});

test('employee affiliation rejects employment period owned by another employee', function () {
    $employee = Employee::factory()->create();
    $otherEmployee = Employee::factory()->create();
    $organization = Organization::factory()->create();
    $employment = EmployeeEmployment::factory()->for($otherEmployee)->create();

    expect(fn () => EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'is_primary' => true,
        'start_date' => '2024-01-01',
        'end_date' => null,
    ]))->toThrow(InvalidArgumentException::class, 'employee_employment_id must belong to the same employee_id.');
});
