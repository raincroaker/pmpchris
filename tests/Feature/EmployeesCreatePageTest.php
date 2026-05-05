<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
});

function createRootForOrganization(Organization $organization): OrganizationalUnit
{
    $unitType = UnitType::factory()->create([
        'name' => 'RootType'.uniqid(),
        'can_be_root' => true,
        'is_active' => true,
    ]);

    return OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);
}

test('guests are redirected to the login page', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('employees.create'));
    $response->assertRedirect(route('login'));
});

test('authorized users can visit the add employee page', function () {
    /** @var \Tests\TestCase $this */
    $organization = Organization::factory()->create(['code' => 'T-EMP-AUTH', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-AUTH']);
    $root = createRootForOrganization($organization);
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->get(route('employees.create'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('allowOrgWideAffiliation', true));
});

test('non hr roles cannot visit add employee page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('employees.create'))
        ->assertForbidden();
});

test('add employee page includes active positions for default organization', function () {
    /** @var \Tests\TestCase $this */
    $organization = Organization::factory()->create([
        'code' => 'T-EMP-CREATE',
        'name' => 'Test Org Create',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-EMP-CREATE']);

    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ENG',
        'title' => 'Engineer',
        'is_active' => true,
    ]);
    $root = createRootForOrganization($organization);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Create')
            ->has('positions', 1)
            ->where('positions.0.code', 'ENG')
            ->where('positions.0.title', 'Engineer'));
});

test('add employee page includes affiliation organization and selectable root units', function () {
    /** @var \Tests\TestCase $this */
    $organization = Organization::factory()->create([
        'code' => 'T-EMP-AFFIL',
        'name' => 'Affil Test Org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-EMP-AFFIL']);

    $root = createRootForOrganization($organization);
    $root->update([
        'parent_id' => null,
        'code' => 'RT-ROOT',
        'name' => 'Root Unit Alpha',
    ]);

    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Create')
            ->where('affiliationOrganization.id', $organization->id)
            ->where('affiliationOrganization.code', 'T-EMP-AFFIL')
            ->has('affiliationRoots', 1)
            ->where('affiliationRoots.0.code', 'RT-ROOT')
            ->where('affiliationRoots.0.name', 'Root Unit Alpha'));
});

test('hr manager only sees managed branches in affiliation roots', function () {
    /** @var \Tests\TestCase $this */
    $organization = Organization::factory()->create([
        'code' => 'T-EMP-MGR',
        'name' => 'Manager Scope Org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-EMP-MGR']);

    $allowedRoot = createRootForOrganization($organization);
    $allowedRoot->update([
        'code' => 'ROOT-ALLOW',
        'name' => 'Allowed Root',
    ]);

    $blockedRoot = createRootForOrganization($organization);
    $blockedRoot->update([
        'code' => 'ROOT-BLOCK',
        'name' => 'Blocked Root',
    ]);

    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $manager->id,
        'root_unit_id' => $allowedRoot->id,
        'is_active' => true,
    ]);

    $this->actingAs($manager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $allowedRoot->id])
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('allowOrgWideAffiliation', false)
            ->has('affiliationRoots', 1)
            ->where('affiliationRoots.0.code', 'ROOT-ALLOW'));
});

test('hr manager add employee options exclude affiliated but unmanaged roots', function () {
    /** @var \Tests\TestCase $this */
    $organization = Organization::factory()->create([
        'code' => 'T-EMP-MGR-STRICT',
        'name' => 'Manager Strict Org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-EMP-MGR-STRICT']);

    $managedRoot = createRootForOrganization($organization);
    $managedRoot->update(['code' => 'ROOT-MANAGED', 'name' => 'Managed Root']);
    $affiliatedOnlyRoot = createRootForOrganization($organization);
    $affiliatedOnlyRoot->update(['code' => 'ROOT-AFFIL', 'name' => 'Affiliated Root']);

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);

    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create([
        'employee_id' => $employee->id,
    ]);
    BranchManager::query()->create([
        'user_id' => $manager->id,
        'root_unit_id' => $managedRoot->id,
        'is_active' => true,
    ]);
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $affiliatedOnlyRoot->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($manager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $affiliatedOnlyRoot->id])
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('affiliationRoots', 1)
            ->where('affiliationRoots.0.code', 'ROOT-MANAGED'));
});

test('hr head plus hr manager sees full affiliation options on add employee page', function () {
    /** @var \Tests\TestCase $this */
    $organization = Organization::factory()->create([
        'code' => 'T-EMP-MIXED',
        'name' => 'Mixed Role Org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-EMP-MIXED']);

    $rootA = createRootForOrganization($organization);
    $rootA->update(['code' => 'ROOT-A', 'name' => 'Root A']);
    $rootB = createRootForOrganization($organization);
    $rootB->update(['code' => 'ROOT-B', 'name' => 'Root B']);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD, Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $rootA->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $rootA->id])
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('allowOrgWideAffiliation', true)
            ->has('affiliationRoots', 2));
});
