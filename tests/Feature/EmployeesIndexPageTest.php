<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeContact;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createCurrentEmployment(Employee $employee): void
{
    EmployeeEmployment::factory()->for($employee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
        'separation_reason' => null,
    ]);
}

function createUser(): User
{
    /** @var User $user */
    $user = User::factory()->create();

    return $user;
}

test('guests are redirected from employees index', function () {
    $this->get(route('employees'))->assertRedirect(route('login'));
});

test('authenticated users can visit the employees index page', function () {
    $user = createUser();
    $this->actingAs($user)
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees')
            ->has('employees.data')
            ->has('filters')
            ->has('positionFilterOptions')
            ->has('unitFilterOptions')
            ->where('filters.sort', 'last_name')
            ->where('filters.direction', 'asc')
            ->where('filters.per_page', 10));
});

test('employees index returns empty paginator when no default organization', function () {
    config(['hris.default_organization_code' => '']);

    $user = createUser();
    $this->actingAs($user)
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->where('organization', null)
            ->has('positionFilterOptions', 0)
            ->has('unitFilterOptions', 0)
            ->has('employees.data', 0)
            ->where('employees.total', 0));
});

test('employees linked by current position in default org appear in the index', function () {
    config(['hris.default_organization_code' => 'T-EMP-LIST']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-LIST',
        'is_active' => true,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'EMP-POS-1',
        'title' => 'Analyst',
    ]);

    $employee = Employee::factory()->create([
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'id_number' => 'EMP-T-IDX-001',
    ]);
    createCurrentEmployment($employee);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
        'is_primary' => true,
    ]);

    EmployeeContact::factory()->create([
        'employee_id' => $employee->id,
        'category' => 'personal',
        'is_primary' => true,
        'contact_number' => '+639001112233',
        'email' => 'jamie@example.test',
    ]);

    $user = createUser();
    $this->actingAs($user)
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $employee->id)
            ->where('employees.data.0.id_number', 'EMP-T-IDX-001')
            ->where('employees.data.0.positions.0.title', 'Analyst')
            ->where('employees.data.0.contact.phone', '+639001112233')
            ->where('employees.data.0.contact.email', 'jamie@example.test'));
});

test('position_id filter limits rows to employees with that current position', function () {
    config(['hris.default_organization_code' => 'T-EMP-FIL']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-FIL',
        'is_active' => true,
    ]);

    $positionA = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'PA',
        'title' => 'Role A',
    ]);
    $positionB = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'PB',
        'title' => 'Role B',
    ]);

    $e1 = Employee::factory()->create(['last_name' => 'Alpha']);
    $e2 = Employee::factory()->create(['last_name' => 'Beta']);
    createCurrentEmployment($e1);
    createCurrentEmployment($e2);

    EmployeePosition::factory()->create([
        'employee_id' => $e1->id,
        'position_id' => $positionA->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $e2->id,
        'position_id' => $positionB->id,
        'end_date' => null,
    ]);

    $user = createUser();

    $this->actingAs($user)
        ->get(route('employees', ['position_id' => $positionA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $e1->id)
            ->where('filters.position_id', $positionA->id));
});

test('employees index provides position filter options for default organization and defaults to all', function () {
    config(['hris.default_organization_code' => 'T-EMP-OPT']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-OPT',
        'is_active' => true,
    ]);

    $analyst = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ANL',
        'title' => 'Analyst',
    ]);
    $manager = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'MGR',
        'title' => 'Manager',
    ]);

    Position::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'code' => 'OTH',
        'title' => 'Other Org Role',
    ]);

    $user = createUser();

    $this->actingAs($user)
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->where('filters.position_id', null)
            ->has('positionFilterOptions', 2)
            ->where('positionFilterOptions.0.id', $analyst->id)
            ->where('positionFilterOptions.0.code', 'ANL')
            ->where('positionFilterOptions.0.title', 'Analyst')
            ->where('positionFilterOptions.1.id', $manager->id)
            ->where('positionFilterOptions.1.code', 'MGR')
            ->where('positionFilterOptions.1.title', 'Manager'));
});

test('unit_id filter limits rows to employees assigned to that current unit', function () {
    config(['hris.default_organization_code' => 'T-EMP-UNIT-FIL']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-UNIT-FIL',
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $unitType = UnitType::factory()->create([
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'ROOT-UF',
        'name' => 'Root UF',
    ]);

    $unitA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => $root->id,
        'code' => 'UA',
        'name' => 'Unit A',
    ]);
    $unitB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => $root->id,
        'code' => 'UB',
        'name' => 'Unit B',
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $e1 = Employee::factory()->create(['last_name' => 'Alpha']);
    $e2 = Employee::factory()->create(['last_name' => 'Beta']);
    createCurrentEmployment($e1);
    createCurrentEmployment($e2);

    EmployeePosition::factory()->create([
        'employee_id' => $e1->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $e2->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $e1->id,
        'organizational_unit_id' => $unitA->id,
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $e2->id,
        'organizational_unit_id' => $unitB->id,
        'end_date' => null,
    ]);

    $user = createUser();

    $this->actingAs($user)
        ->get(route('employees', ['unit_id' => $unitA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $e1->id)
            ->where('filters.unit_id', $unitA->id));
});

test('picker users see unit filter options only from the selected branch subtree', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-EMP-BU']);

    (new RoleSeeder)->run();

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-BU',
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $childType = UnitType::factory()->create([
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'unit_type_id' => $rootType->id,
        'code' => 'BRA',
        'name' => 'Branch A',
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'unit_type_id' => $rootType->id,
        'code' => 'BRB',
        'name' => 'Branch B',
    ]);

    $childA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => $rootA->id,
        'unit_type_id' => $childType->id,
        'code' => 'A-1',
        'name' => 'A Child',
    ]);
    OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => $rootB->id,
        'unit_type_id' => $childType->id,
        'code' => 'B-1',
        'name' => 'B Child',
    ]);

    $employee = Employee::factory()->create(['last_name' => 'Scoped']);
    createCurrentEmployment($employee);
    EmployeeAffiliation::factory()->for($employee)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $childA->id,
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('unitFilterOptions', 2)
            ->where('unitFilterOptions.0.id', $rootA->id)
            ->where('unitFilterOptions.1.id', $childA->id));
});

test('position_id from another organization is ignored', function () {
    config(['hris.default_organization_code' => 'T-EMP-ORG-A']);

    $orgA = Organization::factory()->create([
        'code' => 'T-EMP-ORG-A',
        'is_active' => true,
    ]);
    $orgB = Organization::factory()->create([
        'code' => 'T-EMP-ORG-B',
        'is_active' => true,
    ]);

    $positionInB = Position::factory()->create([
        'organization_id' => $orgB->id,
        'code' => 'OTHER',
        'title' => 'Other org role',
    ]);

    $positionInA = Position::factory()->create([
        'organization_id' => $orgA->id,
        'code' => 'HOME',
        'title' => 'Home role',
    ]);

    $employee = Employee::factory()->create();
    createCurrentEmployment($employee);
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $positionInA->id,
        'end_date' => null,
    ]);

    $user = createUser();

    $this->actingAs($user)
        ->get(route('employees', ['position_id' => $positionInB->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->where('filters.position_id', null)
            ->has('employees.data', 1));
});

test('search narrows employees by id number', function () {
    config(['hris.default_organization_code' => 'T-EMP-SR']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-SR',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $match = Employee::factory()->create(['id_number' => 'FIND-ME-99']);
    $other = Employee::factory()->create(['id_number' => 'OTHER-00']);
    createCurrentEmployment($match);
    createCurrentEmployment($other);

    foreach ([$match, $other] as $e) {
        EmployeePosition::factory()->create([
            'employee_id' => $e->id,
            'position_id' => $position->id,
            'end_date' => null,
        ]);
    }

    $user = createUser();

    $this->actingAs($user)
        ->get(route('employees', ['search' => 'FIND-ME']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $match->id));
});

test('picker users only see employees affiliated to the session branch root', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-EMP-BR']);

    (new RoleSeeder)->run();

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-BR',
        'is_active' => true,
    ]);

    $unitType = UnitType::factory()->create([
        'name' => 'Branch-T-EMP-BR-'.uniqid('', true),
        'can_be_root' => true,
        'is_active' => true,
    ]);

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'is_active' => true,
        'unit_type_id' => $unitType->id,
        'code' => 'ROOT-A-T',
        'name' => 'Branch A Test',
    ]);

    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'is_active' => true,
        'unit_type_id' => $unitType->id,
        'code' => 'ROOT-B-T',
        'name' => 'Branch B Test',
    ]);

    $employeeOnA = Employee::factory()->create(['last_name' => 'OnAlpha']);
    $employeeOnB = Employee::factory()->create(['last_name' => 'OnBeta']);
    createCurrentEmployment($employeeOnA);
    createCurrentEmployment($employeeOnB);

    EmployeeAffiliation::factory()->for($employeeOnA)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    EmployeeAffiliation::factory()->for($employeeOnB)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $rootB->id,
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $employeeOnA->id)
            ->where('branchScope.id', $rootA->id));
});

test('picker users also see employees with org-wide affiliation (null root_unit_id)', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-EMP-BR-NR']);

    (new RoleSeeder)->run();

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-BR-NR',
        'is_active' => true,
    ]);

    $unitType = UnitType::factory()->create([
        'name' => 'Branch-T-EMP-BR-NR-'.uniqid('', true),
        'can_be_root' => true,
        'is_active' => true,
    ]);

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'is_active' => true,
        'unit_type_id' => $unitType->id,
        'code' => 'ROOT-A-NR',
        'name' => 'Branch A NR',
    ]);

    $employeeOnA = Employee::factory()->create(['last_name' => 'OnAlpha']);
    $employeeOrgWide = Employee::factory()->create(['last_name' => 'OrgWide']);
    createCurrentEmployment($employeeOnA);
    createCurrentEmployment($employeeOrgWide);

    EmployeeAffiliation::factory()->for($employeeOnA)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    EmployeeAffiliation::factory()->for($employeeOrgWide)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 2)
            ->where('employees.data.0.id', $employeeOnA->id)
            ->where('employees.data.0.is_org_wide', false)
            ->where('employees.data.1.id', $employeeOrgWide->id)
            ->where('employees.data.1.is_org_wide', true)
            ->where('branchScope.id', $rootA->id));
});

test('employees index can filter only org-wide employees', function () {
    config(['hris.default_organization_code' => 'T-EMP-ORGW']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-ORGW',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ORGW',
        'title' => 'Org Scope Role',
    ]);

    $orgWide = Employee::factory()->create(['last_name' => 'OrgWide']);
    $branchScoped = Employee::factory()->create(['last_name' => 'BranchScoped']);
    createCurrentEmployment($orgWide);
    createCurrentEmployment($branchScoped);

    EmployeeAffiliation::factory()->for($orgWide)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'end_date' => null,
    ]);
    EmployeeAffiliation::factory()->for($branchScoped)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $orgWide->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $branchScoped->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    $user = createUser();

    $this->actingAs($user)
        ->get(route('employees', ['org_scope' => 'org_wide']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->where('filters.org_scope', 'org_wide')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $orgWide->id)
            ->where('employees.data.0.is_org_wide', true));
});

test('employees index can filter only non org-wide employees', function () {
    config(['hris.default_organization_code' => 'T-EMP-BSC']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-BSC',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'BSC',
        'title' => 'Branch Scope Role',
    ]);

    $orgWide = Employee::factory()->create(['last_name' => 'OrgWide']);
    $branchScoped = Employee::factory()->create(['last_name' => 'BranchScoped']);
    createCurrentEmployment($orgWide);
    createCurrentEmployment($branchScoped);

    EmployeeAffiliation::factory()->for($orgWide)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'end_date' => null,
    ]);
    EmployeeAffiliation::factory()->for($branchScoped)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $orgWide->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $branchScoped->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    $user = createUser();

    $this->actingAs($user)
        ->get(route('employees', ['org_scope' => 'branch_scoped']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->where('filters.org_scope', 'branch_scoped')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $branchScoped->id)
            ->where('employees.data.0.is_org_wide', false));
});

test('picker users only see active unit assignments inside selected branch subtree', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-EMP-BR-U']);

    (new RoleSeeder)->run();

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-BR-U',
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $childType = UnitType::factory()->create([
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'unit_type_id' => $rootType->id,
        'code' => 'BRA-U',
        'name' => 'Branch A U',
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'unit_type_id' => $rootType->id,
        'code' => 'BRB-U',
        'name' => 'Branch B U',
    ]);
    $unitA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => $rootA->id,
        'unit_type_id' => $childType->id,
        'code' => 'A-U1',
        'name' => 'A Unit 1',
    ]);
    $unitB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => $rootB->id,
        'unit_type_id' => $childType->id,
        'code' => 'B-U1',
        'name' => 'B Unit 1',
    ]);

    $employeeWithBranchUnit = Employee::factory()->create(['last_name' => 'WithBranchUnit']);
    $employeeWithOtherBranchUnit = Employee::factory()->create(['last_name' => 'WithOtherBranchUnit']);
    $employeeNoUnit = Employee::factory()->create(['last_name' => 'NoUnit']);
    createCurrentEmployment($employeeWithBranchUnit);
    createCurrentEmployment($employeeWithOtherBranchUnit);
    createCurrentEmployment($employeeNoUnit);

    foreach ([$employeeWithBranchUnit, $employeeWithOtherBranchUnit, $employeeNoUnit] as $employee) {
        EmployeeAffiliation::factory()->for($employee)->create([
            'organization_id' => $organization->id,
            'root_unit_id' => $rootA->id,
            'end_date' => null,
        ]);
    }

    EmployeeAssignment::factory()->create([
        'employee_id' => $employeeWithBranchUnit->id,
        'organizational_unit_id' => $unitA->id,
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $employeeWithOtherBranchUnit->id,
        'organizational_unit_id' => $unitB->id,
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('employees'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('employees.data', 3)
            ->where('employees.data.0.id', $employeeNoUnit->id)
            ->has('employees.data.0.units', 0)
            ->where('employees.data.1.id', $employeeWithBranchUnit->id)
            ->has('employees.data.1.units', 1)
            ->where('employees.data.1.units.0.name', 'A Unit 1')
            ->where('employees.data.2.id', $employeeWithOtherBranchUnit->id)
            ->has('employees.data.2.units', 0)
            ->where('branchScope.id', $rootA->id));
});

test('picker users can filter employees by unassigned unit in selected branch', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-EMP-BR-UF']);

    (new RoleSeeder)->run();

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-BR-UF',
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $childType = UnitType::factory()->create([
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => null,
        'unit_type_id' => $rootType->id,
        'code' => 'BRA-UF',
        'name' => 'Branch A UF',
    ]);
    $unitA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'parent_id' => $rootA->id,
        'unit_type_id' => $childType->id,
        'code' => 'A-UF1',
        'name' => 'A Unit UF1',
    ]);

    $assigned = Employee::factory()->create(['last_name' => 'Assigned']);
    $unassigned = Employee::factory()->create(['last_name' => 'Unassigned']);
    createCurrentEmployment($assigned);
    createCurrentEmployment($unassigned);

    EmployeeAffiliation::factory()->for($assigned)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    EmployeeAffiliation::factory()->for($unassigned)->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $assigned->id,
        'organizational_unit_id' => $unitA->id,
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('employees', ['unit_id' => 'unassigned']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->where('filters.unit_id', 'unassigned')
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $unassigned->id)
            ->has('employees.data.0.units', 0));
});
