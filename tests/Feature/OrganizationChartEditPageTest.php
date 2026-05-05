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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new RoleSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);
});

test('privileged roles can visit the organization chart edit page', function (string $roleCode) {
    /** @var User $user */
    $user = User::factory()->withRoles($roleCode)->create();
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();

    $request = $this->actingAs($user);
    if (in_array($roleCode, [Role::CODE_SUPER_ADMIN, Role::CODE_HR_HEAD], true)) {
        $request = $request->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id]);
    }

    $request
        ->get(route('organization-chart.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrganizationChart/Edit')
            ->has('organization')
            ->where('organization.code', 'PMPC')
            ->has('unitTypes')
            ->has('unitTypes.data')
            ->has('unitTypes.data.0.units')
            ->has('parentTypeOptions')
            ->has('rootUnitFilterOptions')
            ->has('filters')
            ->where('filters.search', '')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
            ->where('filters.per_page', 10)
            ->where('filters.root_unit_filter', null));
})->with([
    Role::CODE_SUPER_ADMIN,
    Role::CODE_HR_HEAD,
]);

test('employee cannot visit the organization chart edit page', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('organization-chart.edit'))
        ->assertForbidden();
});

test('hr manager cannot visit the organization chart edit page when branch context is resolved', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();

    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart.edit'))
        ->assertForbidden();
});

test('hr manager without branch session is redirected to branch selection', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->get(route('organization-chart.edit'))
        ->assertRedirect(route('branch.select'));
});

test('guests cannot visit the organization chart edit page', function (): void {
    $this->get(route('organization-chart.edit'))
        ->assertRedirect(route('login'));
});

test('organization chart edit page search filters unit types by name or description', function () {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    /** @var UnitType $branch */
    $branch = UnitType::query()->where('name', 'Branch')->firstOrFail();
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart.edit', ['search' => 'Regional']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrganizationChart/Edit')
            ->where('filters.search', 'Regional')
            ->where('filters.root_unit_filter', null)
            ->has('parentTypeOptions', fn (Assert $rows) => $rows
                ->each(fn (Assert $row) => $row
                    ->where('id', fn ($id) => is_int($id))
                    ->where('name', fn ($name) => is_string($name) && $name !== '')
                    ->etc()))
            ->has('unitTypes.data', fn (Assert $rows) => $rows
                ->each(fn (Assert $row) => $row
                    ->where('id', fn ($id) => is_int($id))
                    ->where('name', fn (string $name) => $name === $branch->name)
                    ->where('units_count', 4)
                    ->has('units', 4, fn (Assert $unit) => $unit
                        ->where('id', fn ($id) => is_int($id))
                        ->where('name', fn ($name) => is_string($name) && $name !== '')
                        ->where('code', fn ($code) => is_string($code) && $code !== '')
                        ->where('parent_name', null)
                        ->where('is_active', true)
                        ->etc())
                    ->etc())));
});

test('organization chart edit page root unit filter scopes unit counts and listed units', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $regionalType = UnitType::factory()->create([
        'name' => 'Regional Test Type',
        'description' => 'Regional test filter',
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'RT-A',
        'name' => 'Root Test A',
        'is_active' => true,
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'RT-B',
        'name' => 'Root Test B',
        'is_active' => true,
    ]);

    OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $regionalType->id,
        'parent_id' => $rootA->id,
        'code' => 'RTA-CHILD',
        'name' => 'Regional A Child',
        'is_active' => true,
    ]);
    OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $regionalType->id,
        'parent_id' => $rootB->id,
        'code' => 'RTB-CHILD',
        'name' => 'Regional B Child',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $rootA->id])
        ->get(route('organization-chart.edit', [
            'search' => 'Regional test filter',
            'root_unit_filter' => $rootA->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrganizationChart/Edit')
            ->where('filters.root_unit_filter', $rootA->id)
            ->has('rootUnitFilterOptions', fn (Assert $rows) => $rows
                ->where('0.label', 'All units')
                ->etc())
            ->has('unitTypes.data', fn (Assert $rows) => $rows
                ->where('0.name', 'Regional Test Type')
                ->where('0.units_count', 1)
                ->where('0.units.0.code', 'RTA-CHILD')
                ->where('0.units.0.parent_name', 'Root Test A')
                ->etc()));
});
