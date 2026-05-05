<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);
});

function createLifecycleUnitUnderPanabo(): OrganizationalUnit
{
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $department = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();
    $sectionType = UnitType::query()->where('name', 'Section')->firstOrFail();

    return OrganizationalUnit::query()->create([
        'code' => 'PAN-D1-X'.fake()->unique()->numberBetween(10, 99),
        'name' => 'Lifecycle Section '.fake()->unique()->numberBetween(1000, 9999),
        'unit_type_id' => $sectionType->id,
        'organization_id' => $panabo->organization_id,
        'parent_id' => $department->id,
        'is_active' => true,
    ]);
}

test('authorized roles can activate and deactivate unit in managed scope', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();
    $unit = createLifecycleUnitUnderPanabo();

    $superAdmin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $hrManager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $hrManager->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.deactivate', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertOk();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.activate', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertOk();

    $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.deactivate', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertOk();

    $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.activate', ['unit' => $unit->id]), [
            'chart_branch_id' => $tagum->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertForbidden();
});

test('non-privileged roles cannot run lifecycle mutations', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $unit = createLifecycleUnitUnderPanabo();
    $nonPrivileged = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();
    $employee = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($nonPrivileged)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.deactivate', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertForbidden();

    $this->actingAs($employee)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->deleteJson(route('organization-chart.units.destroy', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertForbidden();
});

test('lifecycle mutations reject overall chart scope', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $unit = createLifecycleUnitUnderPanabo();
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.deactivate', ['unit' => $unit->id]), [
            'chart_scope' => 'all',
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chart_scope']);
});

test('deactivate is blocked when active assignments exist', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $unit = createLifecycleUnitUnderPanabo();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);

    EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => null,
    ]);

    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.deactivate', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit']);
});

test('deactivate is blocked when active child units exist', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $parent = createLifecycleUnitUnderPanabo();
    $sectionType = UnitType::query()->where('name', 'Section')->firstOrFail();

    OrganizationalUnit::query()->create([
        'code' => 'PAN-D1-Z'.fake()->unique()->numberBetween(10, 99),
        'name' => 'Active Child Unit '.fake()->unique()->numberBetween(1000, 9999),
        'unit_type_id' => $sectionType->id,
        'organization_id' => $panabo->organization_id,
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.deactivate', ['unit' => $parent->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$parent->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit']);
});

test('delete is blocked when child units exist', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $parent = createLifecycleUnitUnderPanabo();
    $sectionType = UnitType::query()->where('name', 'Section')->firstOrFail();
    OrganizationalUnit::query()->create([
        'code' => 'PAN-D1-Y'.fake()->unique()->numberBetween(10, 99),
        'name' => 'Child Unit '.fake()->unique()->numberBetween(1000, 9999),
        'unit_type_id' => $sectionType->id,
        'organization_id' => $panabo->organization_id,
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->deleteJson(route('organization-chart.units.destroy', ['unit' => $parent->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$parent->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit']);
});

test('delete soft-deletes unit when history references exist', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $unit = createLifecycleUnitUnderPanabo();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);

    $assignment = EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
    ]);
    $assignment->delete();

    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->deleteJson(route('organization-chart.units.destroy', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'soft_deleted');

    $trashed = OrganizationalUnit::withTrashed()->findOrFail($unit->id);
    expect($trashed->deleted_at)->not->toBeNull()
        ->and($trashed->is_active)->toBeFalse();
});

test('delete hard-deletes unit when no references exist', function (): void {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $unit = createLifecycleUnitUnderPanabo();
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->deleteJson(route('organization-chart.units.destroy', ['unit' => $unit->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$unit->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'hard_deleted');

    expect(OrganizationalUnit::withTrashed()->whereKey($unit->id)->exists())->toBeFalse();
});
