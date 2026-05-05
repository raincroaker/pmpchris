<?php

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee employment factory persists expected defaults', function () {
    $employment = EmployeeEmployment::factory()->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    expect($employment->exists)->toBeTrue()
        ->and($employment->uuid)->not->toBeEmpty()
        ->and($employment->employment_status)->toBe(EmployeeEmployment::STATUS_ACTIVE)
        ->and($employment->is_current)->toBeTrue()
        ->and($employment->separation_date)->toBeNull();
});

test('employee has many employment records and one current employment relation', function () {
    $employee = Employee::factory()->create();

    EmployeeEmployment::factory()->for($employee)->resigned()->create();
    $current = EmployeeEmployment::factory()->for($employee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    $employee->refresh();

    expect($employee->employments)->toHaveCount(2)
        ->and($employee->currentEmployment)->not->toBeNull()
        ->and($employee->currentEmployment?->id)->toBe($current->id);
});

test('saving a current employment clears previous current flag for same employee', function () {
    $employee = Employee::factory()->create();

    $first = EmployeeEmployment::factory()->for($employee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);
    $second = EmployeeEmployment::factory()->for($employee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    $first->refresh();
    $second->refresh();

    expect($first->is_current)->toBeFalse()
        ->and($second->is_current)->toBeTrue()
        ->and(
            EmployeeEmployment::query()
                ->where('employee_id', $employee->id)
                ->where('is_current', true)
                ->count()
        )->toBe(1);
});
