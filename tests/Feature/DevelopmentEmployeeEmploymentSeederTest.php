<?php

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use Database\Seeders\DevelopmentEmployeeEmploymentSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('development employee employment seeder creates diverse deterministic statuses', function () {
    (new RoleSeeder)->run();
    (new DevelopmentUserSeeder)->run();

    (new DevelopmentEmployeeEmploymentSeeder)->run();

    expect(
        Employee::query()->where('id_number', 'like', 'EMP-SEED-%')->count()
    )->toBe(21);

    $statuses = EmployeeEmployment::query()
        ->whereHas('employee', fn ($query) => $query->where('id_number', 'like', 'EMP-SEED-%'))
        ->pluck('employment_status')
        ->unique()
        ->values()
        ->all();

    expect($statuses)->toContain(EmployeeEmployment::STATUS_ACTIVE)
        ->toContain(EmployeeEmployment::STATUS_RESIGNED)
        ->toContain(EmployeeEmployment::STATUS_TERMINATED)
        ->toContain(EmployeeEmployment::STATUS_RETIRED)
        ->toContain(EmployeeEmployment::STATUS_CONTRACT_ENDED);

    $resignedEmployee = Employee::query()->where('id_number', 'EMP-SEED-012')->firstOrFail();
    $resignedEmployeeTwo = Employee::query()->where('id_number', 'EMP-SEED-019')->firstOrFail();
    $contractEndedInternOne = Employee::query()->where('id_number', 'EMP-SEED-010')->firstOrFail();
    $contractEndedInternTwo = Employee::query()->where('id_number', 'EMP-SEED-011')->firstOrFail();
    $terminatedEmployee = Employee::query()->where('id_number', 'EMP-SEED-013')->firstOrFail();
    $retiredEmployee = Employee::query()->where('id_number', 'EMP-SEED-007')->firstOrFail();
    $contractEndedEmployee = Employee::query()->where('id_number', 'EMP-SEED-014')->firstOrFail();

    expect(
        EmployeeEmployment::query()
            ->where('employee_id', $resignedEmployee->id)
            ->where('employment_status', EmployeeEmployment::STATUS_RESIGNED)
            ->where('is_current', false)
            ->exists()
    )->toBeTrue()
        ->and(
            EmployeeEmployment::query()
                ->where('employee_id', $resignedEmployeeTwo->id)
                ->where('employment_status', EmployeeEmployment::STATUS_RESIGNED)
                ->where('is_current', false)
                ->exists()
        )->toBeTrue()
        ->and(
            EmployeeEmployment::query()
                ->where('employee_id', $contractEndedInternOne->id)
                ->where('employment_status', EmployeeEmployment::STATUS_CONTRACT_ENDED)
                ->where('is_current', false)
                ->exists()
        )->toBeTrue()
        ->and(
            EmployeeEmployment::query()
                ->where('employee_id', $contractEndedInternTwo->id)
                ->where('employment_status', EmployeeEmployment::STATUS_CONTRACT_ENDED)
                ->where('is_current', false)
                ->exists()
        )->toBeTrue()
        ->and(
            EmployeeEmployment::query()
                ->where('employee_id', $terminatedEmployee->id)
                ->where('employment_status', EmployeeEmployment::STATUS_TERMINATED)
                ->where('is_current', false)
                ->exists()
        )->toBeTrue()
        ->and(
            EmployeeEmployment::query()
                ->where('employee_id', $retiredEmployee->id)
                ->where('employment_status', EmployeeEmployment::STATUS_RETIRED)
                ->where('is_current', false)
                ->exists()
        )->toBeTrue()
        ->and(
            EmployeeEmployment::query()
                ->where('employee_id', $contractEndedEmployee->id)
                ->where('employment_status', EmployeeEmployment::STATUS_CONTRACT_ENDED)
                ->where('is_current', false)
                ->exists()
        )->toBeTrue();
});

test('development employee employment seeder is idempotent', function () {
    (new RoleSeeder)->run();
    (new DevelopmentUserSeeder)->run();

    (new DevelopmentEmployeeEmploymentSeeder)->run();

    $countAfterFirst = EmployeeEmployment::query()
        ->whereHas('employee', fn ($query) => $query->where('id_number', 'like', 'EMP-SEED-%'))
        ->count();

    (new DevelopmentEmployeeEmploymentSeeder)->run();

    $countAfterSecond = EmployeeEmployment::query()
        ->whereHas('employee', fn ($query) => $query->where('id_number', 'like', 'EMP-SEED-%'))
        ->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});
