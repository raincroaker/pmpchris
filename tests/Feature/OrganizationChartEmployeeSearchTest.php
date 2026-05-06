<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

function createEmployeeForChartBranch(
    OrganizationalUnit $root,
    OrganizationalUnit $assignedUnit,
    string $firstName,
    string $idNumber,
): Employee {
    $employee = Employee::factory()->create([
        'first_name' => $firstName,
        'last_name' => 'Tester',
        'middle_name' => null,
        'suffix' => null,
        'id_number' => $idNumber,
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $root->organization_id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $assignedUnit->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $root->organization_id,
        'title' => 'Branch Staff',
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
    ]);

    return $employee;
}

function createOrgWideEmployee(OrganizationalUnit $root, string $firstName, string $idNumber): Employee
{
    $employee = Employee::factory()->create([
        'first_name' => $firstName,
        'last_name' => 'Global',
        'middle_name' => null,
        'suffix' => null,
        'id_number' => $idNumber,
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $root->organization_id,
        'root_unit_id' => null,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $root->organization_id,
        'title' => 'Org-wide Staff',
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
    ]);

    return $employee;
}

test('hr head can search chart employees filtered by chart branch and query', function () {
    /** @var TestCase $this */
    config(['hris.default_organization_code' => 'PMPC']);
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D2-S1')->firstOrFail();
    $tagumSection = OrganizationalUnit::query()->where('code', 'TAG-D1-S1')->firstOrFail();

    createEmployeeForChartBranch($panabo, $panaboSection, 'Alice', 'EMP-ALICE');
    createEmployeeForChartBranch($tagum, $tagumSection, 'Bruno', 'EMP-BRUNO');

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $panabo->id,
            'q' => 'Ali',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Alice Tester')
        ->assertJsonPath('data.0.employee_number', 'EMP-ALICE')
        ->assertJsonPath('data.0.positions.0.title', 'Branch Staff');
});

test('employee search endpoint forbids hr manager on unmanaged branch', function () {
    /** @var TestCase $this */
    config(['hris.default_organization_code' => 'PMPC']);
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();

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
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $tagum->id,
            'q' => 'Any',
        ]))
        ->assertForbidden();
});

test('employee search endpoint returns 404 when node is outside chart root', function () {
    /** @var TestCase $this */
    config(['hris.default_organization_code' => 'PMPC']);
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $tagumSection = OrganizationalUnit::query()->where('code', 'TAG-D1-S1')->firstOrFail();

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$tagumSection->id,
        ]))
        ->assertNotFound();
});

test('employee search endpoint includes current user and excludes already assigned employees from selected unit', function () {
    /** @var TestCase $this */
    config(['hris.default_organization_code' => 'PMPC']);
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D2-S1')->firstOrFail();
    $panaboOtherSection = OrganizationalUnit::query()->where('code', 'PAN-D2-S2')->firstOrFail();

    $selfEmployee = createEmployeeForChartBranch($panabo, $panaboOtherSection, 'Self', 'EMP-SELF');
    createEmployeeForChartBranch($panabo, $panaboSection, 'AlreadyAssigned', 'EMP-ASSIGNED');
    createEmployeeForChartBranch($panabo, $panaboOtherSection, 'Available', 'EMP-AVAILABLE');

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create([
        'employee_id' => $selfEmployee->id,
    ]);

    $response = $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'q' => 'EMP-',
        ]))
        ->assertOk();

    $returnedNumbers = collect($response->json('data'))->pluck('employee_number')->all();
    expect($returnedNumbers)->toContain('EMP-AVAILABLE')
        ->toContain('EMP-SELF')
        ->not->toContain('EMP-ASSIGNED');
});

test('employee search includes branch and org-wide employees for hr head and branch manager', function () {
    /** @var TestCase $this */
    config(['hris.default_organization_code' => 'PMPC']);
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();

    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D2-S1')->firstOrFail();

    createOrgWideEmployee($panabo, 'Orgwide', 'EMP-ORGWIDE');
    createEmployeeForChartBranch($panabo, $panaboSection, 'Branch', 'EMP-BRANCH');

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $panabo->id,
            'q' => 'EMP-',
        ]))
        ->assertOk()
        ->assertJsonFragment(['employee_number' => 'EMP-ORGWIDE'])
        ->assertJsonFragment(['employee_number' => 'EMP-BRANCH']);

    /** @var User $hrManager */
    $hrManager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $hrManager->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $panabo->id,
            'q' => 'EMP-',
        ]))
        ->assertOk();

    $returnedNumbers = collect($response->json('data'))->pluck('employee_number')->all();
    expect($returnedNumbers)->toContain('EMP-BRANCH')
        ->toContain('EMP-ORGWIDE');
});
