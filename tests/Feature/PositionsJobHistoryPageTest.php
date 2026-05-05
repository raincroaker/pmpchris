<?php

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('guests are redirected to login from positions job history page', function (): void {
    $this->get(route('positions.job-history'))
        ->assertRedirect(route('login'));
});

test('employee role cannot access positions job history page', function (): void {
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('positions.job-history'))
        ->assertForbidden();
});

test('positions job history defaults to positions history type and exposes table filters', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-JH',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-JH']);

    $positionA = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'POS-ACT',
        'title' => 'Active Position',
    ]);
    $positionI = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'POS-IN',
        'title' => 'Inactive Position',
    ]);

    $activeEmployee = Employee::factory()->create([
        'first_name' => 'Ana',
        'middle_name' => null,
        'last_name' => 'Active',
        'suffix' => null,
        'id_number' => 'EMP-ACT-001',
    ]);
    $inactiveEmployee = Employee::factory()->create([
        'first_name' => 'Ian',
        'middle_name' => null,
        'last_name' => 'Inactive',
        'suffix' => null,
        'id_number' => 'EMP-IN-001',
    ]);

    $activeEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $activeEmployee->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);
    $inactiveEmployment = EmployeeEmployment::factory()->resigned()->create([
        'employee_id' => $inactiveEmployee->id,
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'is_current' => false,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $activeEmployee->id,
        'employee_employment_id' => $activeEmployment->id,
        'position_id' => $positionA->id,
        'start_date' => '2025-01-01',
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $inactiveEmployee->id,
        'employee_employment_id' => $inactiveEmployment->id,
        'position_id' => $positionI->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->get(route('positions.job-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Positions/JobHistory')
            ->where('organization.code', 'T-POS-JH')
            ->where('filters.history_type', 'positions')
            ->where('filters.sort', 'start_date')
            ->where('filters.direction', 'desc')
            ->where('jobHistory.total', 2)
            ->where('jobHistory.data.0.employee.display_name', 'Ana Active')
            ->where('jobHistory.data.0.position.code', 'POS-ACT')
            ->where('jobHistory.data.0.job_status', 'current'));
});

test('positions job history can switch to unit assignments history type', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-JH-UA',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-JH-UA']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'POS-ONE',
    ]);
    $unit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'UNIT-OPS',
        'name' => 'Operations',
    ]);

    $activeEmployee = Employee::factory()->create([
        'first_name' => 'Ana',
        'middle_name' => null,
        'last_name' => 'Active',
        'suffix' => null,
    ]);
    $inactiveEmployee = Employee::factory()->create([
        'first_name' => 'Ian',
        'middle_name' => null,
        'last_name' => 'Inactive',
        'suffix' => null,
    ]);

    $activeEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $activeEmployee->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);
    $inactiveEmployment = EmployeeEmployment::factory()->resigned()->create([
        'employee_id' => $inactiveEmployee->id,
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'is_current' => false,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $activeEmployee->id,
        'employee_employment_id' => $activeEmployment->id,
        'position_id' => $position->id,
        'start_date' => '2025-01-01',
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $inactiveEmployee->id,
        'employee_employment_id' => $inactiveEmployment->id,
        'position_id' => $position->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $inactiveEmployee->id,
        'employee_employment_id' => $inactiveEmployment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->get(route('positions.job-history', ['history_type' => 'unit_assignments']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Positions/JobHistory')
            ->where('filters.history_type', 'unit_assignments')
            ->where('jobHistory.total', 1)
            ->where('jobHistory.data.0.employee.display_name', 'Ian Inactive')
            ->where('jobHistory.data.0.organizational_unit.code', 'UNIT-OPS')
            ->where('jobHistory.data.0.position', null)
            ->where('jobHistory.data.0.job_status', 'ended')
            ->where('jobHistory.data.0.total_days', 31));
});
