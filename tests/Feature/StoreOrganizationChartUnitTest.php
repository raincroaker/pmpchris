<?php

use App\Models\Area;
use App\Models\BranchManager;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\UnitTypeParent;
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

test('hr head can create unit under managed chart branch subtree', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();
    $sectionType = UnitType::query()->where('name', 'Section')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'unit_type_name' => $sectionType->name,
            'name' => 'Compensation Section',
            'code' => 'PAN-D1-S99',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.code', 'PAN-D1-S99');

    $created = OrganizationalUnit::query()
        ->where('organization_id', $panabo->organization_id)
        ->where('code', 'PAN-D1-S99')
        ->firstOrFail();

    expect((int) $created->parent_id)->toBe((int) $panaboDepartment->id)
        ->and((int) $created->unit_type_id)->toBe((int) $sectionType->id);
});

test('hr manager cannot create unit on unmanaged chart branch', function () {
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
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $tagum->id,
            'node_id' => 'unit-'.$tagumDepartment->id,
            'unit_type_name' => 'Section',
            'name' => 'Tagum Extra Section',
            'code' => 'TAG-D1-S99',
        ])
        ->assertForbidden();
});

test('hr manager cannot create unit on organization node', function () {
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
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'organization-'.$organization->id,
            'unit_type_name' => 'Branch',
            'name' => 'Restricted Root',
            'code' => 'RST',
        ])
        ->assertForbidden();
});

test('create unit rejects invalid parent child unit type pairing', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();
    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    /** @var User $admin */
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'unit_type_name' => $branchType->name,
            'name' => 'Invalid Child',
            'code' => 'PAN-D1-X01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_type_name']);
});

test('create unit rejects duplicate code within organization', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $admin */
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'unit_type_name' => 'Section',
            'name' => 'Duplicate Code Section',
            'code' => 'PAN-D1-S1',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('create unit allows same code in different organization', function () {
    $otherOrg = Organization::factory()->create(['code' => 'T-OTHER-ORG', 'is_active' => true]);
    $rootType = UnitType::factory()->create(['can_be_root' => true, 'is_active' => true, 'name' => 'RootTestType']);
    $childType = UnitType::factory()->create(['can_be_root' => false, 'is_active' => true, 'name' => 'ChildTestType']);
    UnitTypeParent::query()->create([
        'parent_unit_type_id' => $rootType->id,
        'child_unit_type_id' => $childType->id,
        'is_active' => true,
    ]);

    OrganizationalUnit::factory()->create([
        'organization_id' => $otherOrg->id,
        'unit_type_id' => $childType->id,
        'parent_id' => null,
        'code' => 'OTHER-ONLY-CODE',
        'name' => 'Other Org Existing Code',
        'is_active' => true,
    ]);

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();

    /** @var User $admin */
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'unit_type_name' => 'Section',
            'name' => 'Allowed Same Code Different Org',
            'code' => 'OTHER-ONLY-CODE',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.code', 'OTHER-ONLY-CODE');
});

test('create unit rejects overall chart scope context', function () {
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboDepartment = OrganizationalUnit::query()->where('code', 'PAN-D1')->firstOrFail();
    $sectionType = UnitType::query()->where('name', 'Section')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.units.store'), [
            'chart_scope' => 'all',
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboDepartment->id,
            'unit_type_name' => $sectionType->name,
            'name' => 'Blocked Overall Scope Unit',
            'code' => 'PAN-D1-S98',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chart_scope']);
});

test('create unit under organization requires area selection', function () {
    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    /** @var User $admin */
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'organization-'.$organization->id,
            'unit_type_name' => $branchType->name,
            'name' => 'New Root Without Area',
            'code' => 'NRA',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['area_id']);
});

test('create unit under organization stores selected area', function () {
    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $area = Area::query()
        ->where('organization_id', $organization->id)
        ->where('is_active', true)
        ->firstOrFail();

    /** @var User $admin */
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.units.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'organization-'.$organization->id,
            'unit_type_name' => $branchType->name,
            'name' => 'New Root With Area',
            'code' => 'NWA',
            'area_id' => $area->id,
        ])
        ->assertSuccessful();

    $created = OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'NWA')
        ->firstOrFail();

    expect((int) $created->area_id)->toBe((int) $area->id);
});
