<?php

use App\Models\Area;
use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(RefreshDatabase::class);

function createOrgWithBranchRoot(): OrganizationalUnit
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ORG',
        'name' => 'Test Cooperative',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-ORG']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    return OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-TEST',
        'name' => 'Test Branch',
        'is_active' => true,
    ]);
}

test('picker user without branch session is redirected from dashboard to branch select', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('branch.select'));
});

test('user without picker roles reaches dashboard without branch session', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('branch store accepts head office root for default organization', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ORG',
        'name' => 'Test Cooperative',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-ORG']);

    $headOfficeType = UnitType::query()->updateOrCreate(
        ['name' => 'Head Office'],
        [
            'color' => '#64748b',
            'can_be_root' => true,
            'description' => 'Head office root',
            'is_active' => true,
        ],
    );

    $headOffice = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $headOfficeType->id,
        'parent_id' => null,
        'code' => 'HO',
        'name' => 'Head Office',
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $headOffice->id,
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertSame($headOffice->id, session(BranchContextService::SESSION_BRANCH_ID));
    $meta = session(BranchContextService::SESSION_BRANCH_META);
    expect($meta)->toBeArray()
        ->and($meta['code'])->toBe('HO')
        ->and($meta['name'])->toBe('Head Office');
});

test('branch store sets session and redirects to dashboard', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    $branch = createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $branch->id,
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertSame($branch->id, session(BranchContextService::SESSION_BRANCH_ID));
    $meta = session(BranchContextService::SESSION_BRANCH_META);
    expect($meta)->toBeArray()
        ->and($meta['code'])->toBe('BR-TEST')
        ->and($meta['name'])->toBe('Test Branch');
});

test('branch store redirects to safe return_to path when provided', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    $branch = createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $branch->id,
        'return_to' => '/employees',
    ]);

    $response->assertRedirect(route('employees', absolute: false));
    $this->assertSame($branch->id, session(BranchContextService::SESSION_BRANCH_ID));
});

test('branch store ignores unsafe return_to and redirects to dashboard', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    $branch = createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $branch->id,
        'return_to' => 'https://evil.example/foo',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $branch->id,
        'return_to' => '//evil.example',
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('branch store rejects branch outside default organization', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    createOrgWithBranchRoot();

    $otherOrg = Organization::factory()->create(['code' => 'OTHER', 'is_active' => true]);
    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $foreignBranch = OrganizationalUnit::factory()->create([
        'organization_id' => $otherOrg->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $foreignBranch->id,
    ])->assertSessionHasErrors('branch_id');
});

test('org-wide employee can access branch picker store', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    $branch = createOrgWithBranchRoot();
    $org = Organization::query()->where('code', 'T-ORG')->firstOrFail();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $employee->id,
    ]);
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $org->id,
        'root_unit_id' => null,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $branch->id,
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('employee without org-wide affiliation cannot access branch picker page', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)->get(route('branch.select'))->assertForbidden();
});

test('employee with single active branch affiliation cannot access branch picker page', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    $branch = createOrgWithBranchRoot();
    $org = Organization::query()->where('code', 'T-ORG')->firstOrFail();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
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

    $this->actingAs($user)->get(route('branch.select'))->assertForbidden();
});

test('employee with single active branch affiliation still receives display branch context', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    $branch = createOrgWithBranchRoot();
    $org = Organization::query()->where('code', 'T-ORG')->firstOrFail();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
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
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSwitchBranches', false)
            ->has('switchableBranches', 0)
            ->where('branchContext.code', 'BR-TEST')
            ->where('branchContext.name', 'Test Branch'));
});

test('employee with two active branch affiliations can pick affiliated roots only', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ORG-MULTI-AFFIL',
        'name' => 'Multi Affiliation Org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ORG-MULTI-AFFIL']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-A',
        'name' => 'Root A',
        'is_active' => true,
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-B',
        'name' => 'Root B',
        'is_active' => true,
    ]);
    $blockedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-C',
        'name' => 'Root C',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $employee->id,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $org->id,
        'root_unit_id' => $rootA->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $org->id,
        'root_unit_id' => $rootB->id,
        'is_primary' => false,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->get(route('branch.select'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('branches', 2)
            ->where('branches.0.code', 'BR-A')
            ->where('branches.1.code', 'BR-B'));

    $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $blockedRoot->id,
    ])->assertSessionHasErrors('branch_id');
});

test('org-wide user can pick all roots even with branch affiliations', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ORG-ORGWIDE-OVERRIDE',
        'name' => 'Org-wide Override Org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ORG-ORGWIDE-OVERRIDE']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-A',
        'name' => 'Root A',
        'is_active' => true,
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-B',
        'name' => 'Root B',
        'is_active' => true,
    ]);
    $rootC = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-C',
        'name' => 'Root C',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $employee->id,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $org->id,
        'root_unit_id' => $rootA->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $org->id,
        'root_unit_id' => null,
        'is_primary' => false,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->get(route('branch.select'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('branches', 3));

    $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $rootC->id,
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('invalid session branch id is cleared when visiting dashboard', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => 999_999])
        ->get(route('dashboard'));

    $response->assertRedirect(route('branch.select'));
    expect(session(BranchContextService::SESSION_BRANCH_ID))->toBeNull();
});

test('branch select page includes area_name on branches when area is linked', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    $branch = createOrgWithBranchRoot();
    $org = Organization::query()->where('code', 'T-ORG')->firstOrFail();
    $area = Area::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Test Area North',
        'is_active' => true,
    ]);
    $branch->update(['area_id' => $area->id]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->get(route('branch.select'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Branch/Select')
            ->has('branches', 1)
            ->where('branches.0.area_name', 'Test Area North')
            ->where('branches.0.group_label', 'Test Area North'));
});

test('picker role user is redirected to branch select after login', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    createOrgWithBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('branch.select'));
});

test('hr manager can pick union of managed and affiliated roots', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ORG-MGR-UNION',
        'name' => 'Test Cooperative',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ORG-MGR-UNION']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $managedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-MANAGED',
        'name' => 'Managed Root',
        'is_active' => true,
    ]);
    $affiliatedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-AFFIL',
        'name' => 'Affiliated Root',
        'is_active' => true,
    ]);
    OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-BLOCK',
        'name' => 'Blocked Root',
        'is_active' => true,
    ]);

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
        'organization_id' => $org->id,
        'root_unit_id' => $affiliatedRoot->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($manager)
        ->get(route('branch.select'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('branches', 2)
            ->where('branches.0.code', 'BR-AFFIL')
            ->where('branches.1.code', 'BR-MANAGED'));
});

test('hr head plus hr manager can pick all roots', function () {
    /** @var TestCase $this */
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ORG-OVERRIDE',
        'name' => 'Override Cooperative',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ORG-OVERRIDE']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-A',
        'name' => 'Root A',
        'is_active' => true,
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-B',
        'name' => 'Root B',
        'is_active' => true,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD, Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $rootA->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('branch.select'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('branches', 2));

    $this->actingAs($user)->post(route('branch.store'), [
        'branch_id' => $rootB->id,
    ])->assertRedirect(route('dashboard', absolute: false));
});
