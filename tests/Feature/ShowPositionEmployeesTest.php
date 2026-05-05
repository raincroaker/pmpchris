<?php

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('guests cannot load position employees json', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-PE-GUEST',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->getJson(route('positions.employees', $position))
        ->assertUnauthorized();
});

test('employee role cannot load position employees json', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-PE-EMP',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->getJson(route('positions.employees', $position))
        ->assertForbidden();
});

test('authenticated privileged user receives current assignees ordered by name', function () {
    config(['hris.default_organization_code' => 'T-PE-OK']);

    $organization = Organization::factory()->create([
        'code' => 'T-PE-OK',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'PE-ROLE',
    ]);

    $employeeZeta = Employee::factory()->create([
        'first_name' => 'Ann',
        'last_name' => 'Zeta',
    ]);
    $employeeAlpha = Employee::factory()->create([
        'first_name' => 'Bob',
        'last_name' => 'Alpha',
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employeeZeta->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $employeeAlpha->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $response = $this->actingAs($user)
        ->getJson(route('positions.employees', $position));

    $response->assertOk()
        ->assertJsonStructure([
            'employees' => [
                '*' => ['id', 'display_name', 'id_number'],
            ],
        ]);

    $employees = $response->json('employees');

    expect($employees)->toHaveCount(2)
        ->and($employees[0]['display_name'])->toContain('Alpha')
        ->and($employees[1]['display_name'])->toContain('Zeta');
});

test('position outside default organization returns not found', function () {
    config(['hris.default_organization_code' => 'T-PE-DEF']);

    Organization::factory()->create([
        'code' => 'T-PE-DEF',
        'is_active' => true,
    ]);
    $otherOrg = Organization::factory()->create([
        'code' => 'T-PE-OTHER',
        'is_active' => true,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $otherOrg->id,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->getJson(route('positions.employees', $position))
        ->assertNotFound();
});

test('ended assignments are excluded', function () {
    config(['hris.default_organization_code' => 'T-PE-END']);

    $organization = Organization::factory()->create([
        'code' => 'T-PE-END',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $current = Employee::factory()->create(['last_name' => 'Current']);
    $past = Employee::factory()->create(['last_name' => 'Past']);

    EmployeePosition::factory()->create([
        'employee_id' => $current->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $past->id,
        'position_id' => $position->id,
        'end_date' => now()->subDay()->toDateString(),
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $employees = $this->actingAs($user)
        ->getJson(route('positions.employees', $position))
        ->assertOk()
        ->json('employees');

    expect($employees)->toHaveCount(1)
        ->and($employees[0]['display_name'])->toContain('Current');
});

test('soft deleted employee positions are excluded', function () {
    config(['hris.default_organization_code' => 'T-PE-SDP']);

    $organization = Organization::factory()->create([
        'code' => 'T-PE-SDP',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $employee = Employee::factory()->create();

    $pivot = EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    $pivot->delete();

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->getJson(route('positions.employees', $position))
        ->assertOk()
        ->assertJsonCount(0, 'employees');
});

test('soft deleted employees are excluded', function () {
    config(['hris.default_organization_code' => 'T-PE-SDE']);

    $organization = Organization::factory()->create([
        'code' => 'T-PE-SDE',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $employee = Employee::factory()->create();
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    $employee->delete();

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->getJson(route('positions.employees', $position))
        ->assertOk()
        ->assertJsonCount(0, 'employees');
});
