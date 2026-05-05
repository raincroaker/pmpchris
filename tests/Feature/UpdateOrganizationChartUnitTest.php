<?php

use App\Models\BranchManager;
use App\Models\Organization;
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

test('super admin can update unit name and code in chart subtree', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $superAdmin */
    $superAdmin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($superAdmin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panaboDepartment->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'name' => 'People Operations',
            'code' => 'pan-d1-people',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'People Operations')
        ->assertJsonPath('data.code', 'PAN-D1-PEOPLE');

    $panaboDepartment->refresh();
    expect($panaboDepartment->name)->toBe('People Operations')
        ->and($panaboDepartment->code)->toBe('PAN-D1-PEOPLE');
});

test('hr head can update unit name and code in chart subtree', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panaboDepartment->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'name' => 'HR Shared Services',
            'code' => 'pan-d1-hrss',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.code', 'PAN-D1-HRSS');
});

test('hr manager can update only units in managed branch subtree', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();
    $tagumDepartment = OrganizationalUnit::query()->where('code', 'TAG-D1')->firstOrFail();

    /** @var User $hrManager */
    $hrManager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $hrManager->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panaboDepartment->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'name' => 'Managed Update',
            'code' => 'PAN-D1-MANAGED',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.code', 'PAN-D1-MANAGED');

    $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $tagumDepartment->id]), [
            'chart_branch_id' => $tagum->id,
            'node_id' => 'unit-'.$tagumDepartment->id,
            'name' => 'Unmanaged Update',
            'code' => 'TAG-D1-UNMANAGED',
        ])
        ->assertForbidden();
});

test('non privileged role cannot update units', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $employee */
    $employee = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($employee)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panaboDepartment->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'name' => 'Employee Update',
            'code' => 'PAN-D1-EMP',
        ])
        ->assertForbidden();
});

test('hr manager cannot update units via organization node payload', function () {
    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();

    /** @var User $hrManager */
    $hrManager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $hrManager->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panabo->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'organization-'.$organization->id,
            'name' => 'Forbidden Root Edit',
            'code' => 'PAN-FORBIDDEN',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['node_id']);
});

test('update unit rejects duplicate code within organization', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panaboDepartment->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'name' => 'Duplicate Attempt',
            'code' => 'PAN',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('update unit allows code used in another organization', function () {
    $otherOrg = Organization::factory()->create(['code' => 'EDIT-OTHER-ORG', 'is_active' => true]);
    $otherRootType = UnitType::factory()->create([
        'name' => 'EditOtherRootType',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    OrganizationalUnit::factory()->create([
        'organization_id' => $otherOrg->id,
        'unit_type_id' => $otherRootType->id,
        'parent_id' => null,
        'code' => 'OTHER-ORG-CODE',
        'name' => 'Other Org Root',
        'is_active' => true,
    ]);

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $admin */
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panaboDepartment->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'name' => 'Cross Org Safe',
            'code' => 'OTHER-ORG-CODE',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.code', 'OTHER-ORG-CODE');
});

test('update unit rejects overall chart scope context', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.units.update', ['unit' => $panaboDepartment->id]), [
            'chart_scope' => 'all',
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'name' => 'Blocked Overall Scope Update',
            'code' => 'PAN-D1-BLOCKED',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chart_scope']);
});
