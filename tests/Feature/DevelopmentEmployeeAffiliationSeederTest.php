<?php

use App\Models\AssignmentPosition;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\DevelopmentEmployeeAffiliationSeeder;
use Database\Seeders\DevelopmentEmployeePositionSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('development employee affiliation seeder creates PAN and TAG affiliations for super admin seed employee', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new DevelopmentUserSeeder)->run();

    (new DevelopmentEmployeeAffiliationSeeder)->run();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $employee = Employee::query()->where('id_number', 'EMP-SEED-001')->firstOrFail();

    $panRoot = \App\Models\OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'PAN')
        ->whereNull('parent_id')
        ->firstOrFail();
    $tagRoot = \App\Models\OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'TAG')
        ->whereNull('parent_id')
        ->firstOrFail();

    expect(
        EmployeeAffiliation::query()
            ->where('employee_id', $employee->id)
            ->where('organization_id', $organization->id)
            ->whereIn('root_unit_id', [$panRoot->id, $tagRoot->id])
            ->whereNull('end_date')
            ->count()
    )->toBe(2);

    $affiliation = EmployeeAffiliation::query()
        ->where('employee_id', $employee->id)
        ->firstOrFail();

    expect($affiliation->employee_employment_id)->not->toBeNull()
        ->and(
            EmployeeEmployment::query()
                ->whereKey($affiliation->employee_employment_id)
                ->where('employee_id', $employee->id)
                ->exists()
        )->toBeTrue();
});

test('development employee affiliation seeder is idempotent for total affiliation rows', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new DevelopmentUserSeeder)->run();

    (new DevelopmentEmployeeAffiliationSeeder)->run();
    $countAfterFirst = EmployeeAffiliation::query()
        ->whereHas('employee', fn ($q) => $q->where('id_number', 'like', 'EMP-SEED-%'))
        ->count();

    (new DevelopmentEmployeeAffiliationSeeder)->run();
    $countAfterSecond = EmployeeAffiliation::query()
        ->whereHas('employee', fn ($q) => $q->where('id_number', 'like', 'EMP-SEED-%'))
        ->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

test('seed employee affiliations are PAN/TAG only (no org-wide rows)', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new DevelopmentUserSeeder)->run();
    (new DevelopmentEmployeeAffiliationSeeder)->run();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $target = Employee::query()->where('id_number', 'EMP-SEED-001')->firstOrFail();

    expect(
        EmployeeAffiliation::query()
            ->where('employee_id', $target->id)
            ->where('organization_id', $organization->id)
            ->whereNull('root_unit_id')
            ->exists()
    )->toBeFalse();
});

test('hr manager seed employee has branch affiliation only not org-wide', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new DevelopmentUserSeeder)->run();
    (new DevelopmentEmployeeAffiliationSeeder)->run();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $tagRoot = \App\Models\OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'TAG')
        ->whereNull('parent_id')
        ->firstOrFail();

    $diana = Employee::query()->where('id_number', 'EMP-SEED-003')->firstOrFail();

    expect(
        EmployeeAffiliation::query()
            ->where('employee_id', $diana->id)
            ->where('organization_id', $organization->id)
            ->whereNull('root_unit_id')
            ->exists()
    )->toBeFalse()
        ->and(
            EmployeeAffiliation::query()
                ->where('employee_id', $diana->id)
                ->where('organization_id', $organization->id)
                ->whereIn('root_unit_id', [$tagRoot->id, \App\Models\OrganizationalUnit::query()
                    ->where('organization_id', $organization->id)
                    ->where('code', 'PAN')
                    ->whereNull('parent_id')
                    ->value('id')])
                ->whereNull('end_date')
                ->count()
        )->toBe(2);
});

test('multi-affiliated line employee has branch affiliations only', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new DevelopmentUserSeeder)->run();
    (new DevelopmentEmployeeAffiliationSeeder)->run();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panRoot = \App\Models\OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'PAN')
        ->whereNull('parent_id')
        ->firstOrFail();
    $tagRoot = \App\Models\OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'TAG')
        ->whereNull('parent_id')
        ->firstOrFail();

    $employee = Employee::query()->where('id_number', 'EMP-SEED-017')->firstOrFail();

    expect(
        EmployeeAffiliation::query()
            ->where('employee_id', $employee->id)
            ->where('organization_id', $organization->id)
            ->whereNull('root_unit_id')
            ->exists()
    )->toBeFalse()
        ->and(
            EmployeeAffiliation::query()
                ->where('employee_id', $employee->id)
                ->where('organization_id', $organization->id)
                ->whereIn('root_unit_id', [$panRoot->id, $tagRoot->id])
                ->count()
        )->toBe(2);
});

test('development employee position seeder links assignment positions for branch-assigned employee', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new DevelopmentUserSeeder)->run();
    (new DevelopmentEmployeeAffiliationSeeder)->run();
    (new DevelopmentEmployeePositionSeeder)->run();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $employee = Employee::query()->where('id_number', 'EMP-SEED-003')->firstOrFail();

    $assignment = EmployeeAssignment::query()
        ->where('employee_id', $employee->id)
        ->whereNull('organization_id')
        ->whereNotNull('organizational_unit_id')
        ->whereNull('end_date')
        ->firstOrFail();

    expect($assignment->employee_employment_id)->not->toBeNull()
        ->and(
            EmployeeEmployment::query()
                ->whereKey($assignment->employee_employment_id)
                ->where('employee_id', $employee->id)
                ->exists()
        )->toBeTrue();

    expect(
        AssignmentPosition::query()
            ->where('employee_assignment_id', $assignment->id)
            ->exists()
    )->toBeTrue();
});
