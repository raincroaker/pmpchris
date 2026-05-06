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
    config(['hris.default_organization_code' => 'PMPC']);
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
});

test('authorized hr roles can check unit code availability', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.units.check-code-availability', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'code' => 'PAN-D2-S1',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('code.status', 'taken');
});

test('non hr roles cannot check unit code availability', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();

    /** @var User $employee */
    $employee = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($employee)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.units.check-code-availability', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panabo->id,
            'code' => 'NEW-CODE',
        ]))
        ->assertForbidden();
});

test('code availability is organization scoped', function () {
    $otherOrg = Organization::factory()->create(['code' => 'OTHER-CODE-ORG', 'is_active' => true]);
    $otherRootType = UnitType::factory()->create([
        'name' => 'OtherRootType',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    OrganizationalUnit::factory()->create([
        'organization_id' => $otherOrg->id,
        'unit_type_id' => $otherRootType->id,
        'parent_id' => null,
        'code' => 'OTHER-ONLY-CODE',
        'name' => 'Other Org Root',
        'is_active' => true,
    ]);

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.units.check-code-availability', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'code' => 'OTHER-ONLY-CODE',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('code.status', 'available');
});

test('code availability can ignore unit id for reuse on edit-like checks', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();

    /** @var User $superAdmin */
    $superAdmin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($superAdmin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.units.check-code-availability', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'organization-'.(int) $panabo->organization_id,
            'code' => 'PAN',
            'ignore_unit_id' => $panabo->id,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('code.status', 'available');
});

test('code availability ignore unit id still rejects collisions with other units', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $superAdmin */
    $superAdmin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($superAdmin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.units.check-code-availability', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'code' => 'PAN',
            'ignore_unit_id' => $panaboDepartment->id,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('code.status', 'taken');
});

test('hr manager is forbidden from checking on unmanaged branch', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
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
        ->getJson(route('organization-chart.units.check-code-availability', [
            'chart_branch_id' => $tagum->id,
            'node_id' => 'unit-'.$tagumDepartment->id,
            'code' => 'TAG-D1-S99',
        ]))
        ->assertForbidden();
});

test('code availability check rejects overall chart scope context', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.units.check-code-availability', [
            'chart_scope' => 'all',
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'code' => 'PAN-D1-S99',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chart_scope']);
});
