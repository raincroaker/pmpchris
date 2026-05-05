<?php

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\Position;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee position factory persists expected attributes', function () {
    $row = EmployeePosition::factory()->create([
        'start_date' => '2024-01-01',
        'end_date' => null,
        'is_primary' => true,
    ]);

    expect($row->exists)->toBeTrue()
        ->and($row->start_date->format('Y-m-d'))->toBe('2024-01-01')
        ->and($row->end_date)->toBeNull()
        ->and($row->is_primary)->toBeTrue();
});

test('position code is unique per organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    Position::factory()->create([
        'organization_id' => $orgA->id,
        'code' => 'POS-ACCT-01',
    ]);

    Position::factory()->create([
        'organization_id' => $orgB->id,
        'code' => 'POS-ACCT-01',
    ]);

    expect(Position::query()->where('code', 'POS-ACCT-01')->count())->toBe(2);
});

test('position code cannot duplicate within same organization', function () {
    $org = Organization::factory()->create();

    Position::factory()->create([
        'organization_id' => $org->id,
        'code' => 'POS-HR-01',
    ]);

    $failed = false;

    try {
        Position::factory()->create([
            'organization_id' => $org->id,
            'code' => 'POS-HR-01',
        ]);
    } catch (QueryException) {
        $failed = true;
    }

    expect($failed)->toBeTrue();
});

test('employee has many positions', function () {
    $employee = Employee::factory()->create();
    EmployeePosition::factory()->count(2)->for($employee)->create();

    expect($employee->positions)->toHaveCount(2);
});

test('employee position must use employment for the same employee', function () {
    $employee = Employee::factory()->create();
    $otherEmployee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $otherEmployee->id,
    ]);

    expect(fn () => EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
    ]))->toThrow(\InvalidArgumentException::class, 'employee_employment_id must belong to the same employee_id.');
});
