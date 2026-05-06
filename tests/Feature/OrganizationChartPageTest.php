<?php

use App\Models\AssignmentPosition;
use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    /** @var TestCase $this */
    $response = $this->get(route('organization-chart'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the organization chart page', function () {
    /** @var TestCase $this */
    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('organization-chart'));
    $response->assertOk();
});

test('organization chart defaults to first selectable branch root when user has no branch picker session', function () {
    /** @var TestCase $this */
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $defaultRoot = app(BranchContextService::class)->defaultChartRoot($organization);
    expect($defaultRoot)->not->toBeNull()
        ->and($defaultRoot->code)->toBe('PAN');

    $orgVueId = 'organization-'.$organization->id;

    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('organization-chart'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrganizationChart')
            ->missing('chartContext')
            ->has('orgChart.nodes')
            ->where('orgChart.nodes.0.id', $orgVueId)
            ->where('orgChart.nodes.0.type', 'org')
            ->where('orgChart.nodes.0.data.fullName', 'Panabo Multipurpose Cooperative')
            ->where('orgChart.nodes.0.data.alias', 'PMPC')
            ->where('orgChart.nodes.1.id', 'unit-'.$defaultRoot->id)
            ->where('orgChart.nodes.1.data.fullName', 'Panabo Branch')
            ->where('orgChart.nodes.1.data.alias', 'PAN')
            ->where('orgChart.nodes.1.data.isActive', true)
            ->where('orgChart.nodes.1.parentId', $orgVueId));
});

test('organization chart uses session branch root when user must select branch', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->firstOrFail();
    $orgVueId = 'organization-'.$organization->id;

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrganizationChart')
            ->missing('chartContext')
            ->where('chartBranchId', $panabo->id)
            ->where('chartCapabilities.canManageBranch', true)
            ->where('chartCapabilities.canManageOrganizationNode', true)
            ->has('chartBranches')
            ->has('orgChart.nodes')
            ->where('orgChart.nodes.0.id', $orgVueId)
            ->where('orgChart.nodes.0.type', 'org')
            ->where('orgChart.nodes.1.id', 'unit-'.$panabo->id)
            ->where('orgChart.nodes.1.data.fullName', 'Panabo Branch')
            ->where('orgChart.nodes.1.data.alias', 'PAN')
            ->where('orgChart.nodes.1.parentId', $orgVueId));
});

test('organization chart supports chart-local branch switch via query without mutating session branch', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();
    $orgVueId = 'organization-'.$organization->id;

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart', ['chart_branch_id' => $tagum->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrganizationChart')
            ->where('chartBranchId', $tagum->id)
            ->where('chartCapabilities.canManageBranch', true)
            ->where('chartCapabilities.canManageOrganizationNode', true)
            ->has('chartBranches')
            ->where('orgChart.nodes.0.id', $orgVueId)
            ->where('orgChart.nodes.1.id', 'unit-'.$tagum->id)
            ->where('orgChart.nodes.1.data.alias', 'TAG'));

    expect(session(BranchContextService::SESSION_BRANCH_ID))->toBe($panabo->id);
});

test('organization chart marks managed branch capabilities for hr manager and denies unmanaged branch', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();

    /** @var User $manager */
    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $manager->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $this->actingAs($manager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart', ['chart_branch_id' => $panabo->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chartCapabilities.canManageBranch', true)
            ->where('chartCapabilities.canManageOrganizationNode', false));

    $this->actingAs($manager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart', ['chart_branch_id' => $tagum->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chartBranchId', $tagum->id)
            ->where('chartCapabilities.canManageBranch', false)
            ->where('chartCapabilities.canManageOrganizationNode', false));
});

test('organization chart overall scope returns multi-branch graph with read-only capabilities', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();
    $orgVueId = 'organization-'.$organization->id;

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart', ['chart_scope' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrganizationChart')
            ->where('chartScope', 'all')
            ->where('chartBranchId', null)
            ->where('chartCapabilities.canManageBranch', false)
            ->where('chartCapabilities.canManageOrganizationNode', false)
            ->where('orgChart.nodes.0.id', $orgVueId)
            ->has('orgChart.nodes'));

    $nodeIds = collect($response->inertiaProps('orgChart.nodes'))
        ->pluck('id')
        ->all();

    expect($nodeIds)
        ->toContain('unit-'.$panabo->id)
        ->toContain('unit-'.$tagum->id);
});

test('organization chart supports active and all unit visibility modes', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $inactiveUnit = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();
    $inactiveUnit->is_active = false;
    $inactiveUnit->save();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $activeOnlyResponse = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart', ['chart_branch_id' => $panabo->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('chartUnitStatus', 'active'));

    $activeOnlyNodeIds = collect($activeOnlyResponse->inertiaProps('orgChart.nodes'))
        ->pluck('id')
        ->all();

    expect($activeOnlyNodeIds)->not->toContain('unit-'.$inactiveUnit->id);

    $allUnitsResponse = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart', [
            'chart_branch_id' => $panabo->id,
            'chart_unit_status' => 'all',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('chartUnitStatus', 'all'));

    $allNodeIds = collect($allUnitsResponse->inertiaProps('orgChart.nodes'))
        ->pluck('id')
        ->all();

    expect($allNodeIds)->toContain('unit-'.$inactiveUnit->id);
});

test('organization chart organization node includes org-level assigned employees', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $employee = Employee::factory()->create([
        'first_name' => 'Orville',
        'last_name' => 'Org',
        'middle_name' => null,
        'suffix' => null,
        'id_number' => 'EMP-ORG-001',
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);

    $assignment = EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'organizational_unit_id' => null,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Chief Executive Officer',
    ]);

    $employeePosition = $employee->positions()->create([
        'position_id' => $position->id,
        'employee_employment_id' => $employment->id,
        'is_primary' => true,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
    ]);

    AssignmentPosition::query()->create([
        'employee_assignment_id' => $assignment->id,
        'employee_position_id' => $employeePosition->id,
        'is_primary_for_assignment' => true,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => now()->addYears(20)->toDateString(),
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->get(route('organization-chart'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orgChart.nodes.0.data.employees.0.full_name', 'Orville Org')
            ->where('orgChart.nodes.0.data.employees.0.position_title', 'Chief Executive Officer'));
});
