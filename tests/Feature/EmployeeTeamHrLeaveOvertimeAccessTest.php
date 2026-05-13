<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seedOrgWithSelectableBranchRoot(): OrganizationalUnit
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-HR-TEAM',
        'name' => 'Test Cooperative HR Team',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-HR-TEAM']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    return OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-HR',
        'name' => 'HR Test Branch',
        'is_active' => true,
    ]);
}

test('employee team leave and overtime pages are forbidden without hr authorization', function (): void {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('leave.team'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('overtime.team'))
        ->assertForbidden();
});

test('hr head may access employee team pages after selecting workspace branch', function (): void {
    (new RoleSeeder)->run();
    $branch = seedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->get(route('leave.team'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leave/Team'));

    $this->actingAs($user)
        ->get(route('overtime.team'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overtime/Team'));
});

test('super admin may access employee team pages after selecting workspace branch', function (): void {
    (new RoleSeeder)->run();
    $branch = seedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('leave.team'))
        ->assertOk();
});

test('hr manager may access employee team pages when assigned as branch manager for workspace branch', function (): void {
    (new RoleSeeder)->run();
    $branch = seedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $branch->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('leave.team'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('overtime.team'))
        ->assertOk();
});

test('hr manager without branch manager assignment cannot access employee team pages', function (): void {
    (new RoleSeeder)->run();
    $branch = seedOrgWithSelectableBranchRoot();
    $org = Organization::query()->where('code', 'T-HR-TEAM')->firstOrFail();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create([
        'employee_id' => $employee->id,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $org->id,
        'root_unit_id' => $branch->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->get(route('leave.team'))
        ->assertForbidden();
});
