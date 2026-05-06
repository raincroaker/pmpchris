<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    config(['hris.branch_picker_enabled' => false]);
});

function employmentHistoryMakeUser(): User
{
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    return $user;
}

/** Current position-linked employee row (directory-visible via non-branch scope). */
function employmentHistoryBootstrapVisibleEmployee(Organization $organization): Employee
{
    $employee = Employee::factory()->create([
        'first_name' => 'Visible',
        'last_name' => 'Person',
        'id_number' => 'EMP-VIS-HIST',
    ]);

    EmployeeEmployment::factory()->for($employee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'hire_date' => '2019-01-01',
        'separation_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'HIST-V-POS',
        'title' => 'Staff',
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
        'is_primary' => true,
    ]);

    return $employee;
}

test('guests are redirected from employment history', function (): void {
    $this->get(route('employees.employment-history'))
        ->assertRedirect(route('login'));
});

test('employee role cannot access employment history', function (): void {
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('employees.employment-history'))
        ->assertForbidden();
});

test('hr manager is redirected from employment history when workspace branch is unmanaged', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-UNMANAGED']);

    Organization::factory()->create([
        'code' => 'T-EMP-HIST-UNMANAGED',
        'is_active' => true,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->get(route('employees.employment-history'))
        ->assertRedirect(route('dashboard'));
});

test('employment history renders empty listing when default organization missing', function (): void {
    config(['hris.default_organization_code' => '']);

    $user = employmentHistoryMakeUser();
    $this->actingAs($user)
        ->get(route('employees.employment-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->where('organization', null)
            ->has('employments.data', 0));
});

test('employment history lists only visible employees employment rows', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-VIS']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-VIS',
        'is_active' => true,
    ]);

    employmentHistoryBootstrapVisibleEmployee($organization);

    $hiddenEmployee = Employee::factory()->create(['last_name' => 'Invisible']);
    EmployeeEmployment::factory()->for($hiddenEmployee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'hire_date' => '2018-01-01',
    ]);

    $user = employmentHistoryMakeUser();
    $this->actingAs($user)
        ->get(route('employees.employment-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 1));
});

test('employment history includes separated employees with historical organization linkage', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-HIST']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-HIST',
        'is_active' => true,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $employee = Employee::factory()->create([
        'first_name' => 'Former',
        'last_name' => 'Staff',
        'id_number' => 'EMP-HIST-OLD',
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2018-01-01',
        'separation_date' => '2020-01-15',
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'is_current' => false,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'start_date' => '2018-01-01',
        'end_date' => '2020-01-15',
        'is_primary' => true,
    ]);

    $user = employmentHistoryMakeUser();
    $this->actingAs($user)
        ->get(route('employees.employment-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 1)
            ->where('employments.data.0.employee.id_number', 'EMP-HIST-OLD')
            ->where('employments.data.0.employment_status', EmployeeEmployment::STATUS_RESIGNED));
});

test('employment history returns multiple rows per employee', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-MULTI']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-MULTI',
        'is_active' => true,
    ]);

    $employee = employmentHistoryBootstrapVisibleEmployee($organization);

    EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2015-01-01',
        'separation_date' => '2017-06-01',
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'is_current' => false,
    ]);

    $user = employmentHistoryMakeUser();
    $this->actingAs($user)
        ->get(route('employees.employment-history', ['sort' => 'hire_date', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 2));
});

test('employment status query filter limits employment rows', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-STAT']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-STAT',
        'is_active' => true,
    ]);

    $employee = employmentHistoryBootstrapVisibleEmployee($organization);

    EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2014-06-01',
        'separation_date' => '2015-06-02',
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'is_current' => false,
    ]);

    $user = employmentHistoryMakeUser();
    $this->actingAs($user)
        ->get(route('employees.employment-history', [
            'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 1));
});

test('employment status query filter supports contract ended rows', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-C-END']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-C-END',
        'is_active' => true,
    ]);

    $employee = employmentHistoryBootstrapVisibleEmployee($organization);

    EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2014-06-01',
        'separation_date' => '2015-06-02',
        'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
        'is_current' => false,
    ]);

    $user = employmentHistoryMakeUser();
    $this->actingAs($user)
        ->get(route('employees.employment-history', [
            'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 1));
});

test('employee id sort orders rows by employee id number', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-ID-SORT']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-ID-SORT',
        'is_active' => true,
    ]);

    $position = Position::factory()->create(['organization_id' => $organization->id]);

    $employeeA = Employee::factory()->create([
        'id_number' => 'EMP-002',
    ]);
    $employmentA = EmployeeEmployment::factory()->create([
        'employee_id' => $employeeA->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $employeeA->id,
        'employee_employment_id' => $employmentA->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    $employeeB = Employee::factory()->create([
        'id_number' => 'EMP-001',
    ]);
    $employmentB = EmployeeEmployment::factory()->create([
        'employee_id' => $employeeB->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $employeeB->id,
        'employee_employment_id' => $employmentB->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    $user = employmentHistoryMakeUser();

    $this->actingAs($user)
        ->get(route('employees.employment-history', ['sort' => 'id_number', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->where('filters.sort', 'id_number')
            ->where('filters.direction', 'asc')
            ->where('employments.data.0.employee.id_number', 'EMP-001')
            ->where('employments.data.1.employee.id_number', 'EMP-002'));
});

test('employment history employee payload includes org_wide when active org-wide affiliation matches employees index rules', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-ORG']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-ORG',
        'is_active' => true,
    ]);

    $employee = employmentHistoryBootstrapVisibleEmployee($organization);

    $currentEmployment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->first();
    expect($currentEmployment)->not->toBeNull();

    EmployeeAffiliation::factory()->for($employee)->create([
        'organization_id' => $organization->id,
        'employee_employment_id' => $currentEmployment->id,
        'root_unit_id' => null,
        'start_date' => '2022-01-01',
        'end_date' => null,
        'is_primary' => false,
    ]);

    $user = employmentHistoryMakeUser();
    $this->actingAs($user)
        ->get(route('employees.employment-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 1)
            ->where('employments.data.0.employment_status', EmployeeEmployment::STATUS_ACTIVE)
            ->where('employments.data.0.employee.is_org_wide', true));
});

test('hire date range keeps rows whose hire date is inside the inclusive range', function (): void {
    config(['hris.default_organization_code' => 'T-EMP-HIST-HIRE']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-HIRE',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id]);

    $currentEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2024-06-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-01-01',
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'separation_date' => '2021-01-01',
        'is_current' => false,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'employee_employment_id' => $currentEmployment->id,
        'end_date' => null,
    ]);

    $user = employmentHistoryMakeUser();

    $this->actingAs($user)
        ->get(route('employees.employment-history', [
            'hire_from' => '2024-01-01',
            'hire_to' => '2024-12-31',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 1));
});

test('separation date range overlaps open employments against today using coalesced separation', function (): void {
    $this->travelTo(now()->parse('2026-05-03')->startOfDay());

    config(['hris.default_organization_code' => 'T-EMP-HIST-SEP']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-SEP',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id]);

    $openEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2025-06-01',
        'separation_date' => null,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'employee_employment_id' => $openEmployment->id,
        'end_date' => null,
    ]);

    $user = employmentHistoryMakeUser();

    $this->actingAs($user)
        ->get(route('employees.employment-history', [
            'separation_from' => '2026-01-01',
            'separation_to' => '2026-01-31',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 1));

    $this->actingAs($user)
        ->get(route('employees.employment-history', [
            'separation_from' => '2025-01-01',
            'separation_to' => '2025-05-31',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 0));

    $this->travelBack();
});

test('tenure days inclusive for separated and ongoing employments', function (): void {
    $this->travelTo(now()->parse('2026-05-15')->startOfDay());

    config(['hris.default_organization_code' => 'T-EMP-HIST-TEN']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-TEN',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id]);

    $ongoing = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2026-05-01',
        'separation_date' => null,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2025-03-01',
        'separation_date' => '2025-03-10',
        'employment_status' => EmployeeEmployment::STATUS_TERMINATED,
        'is_current' => false,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'employee_employment_id' => $ongoing->id,
        'end_date' => null,
    ]);

    $user = employmentHistoryMakeUser();

    $this->actingAs($user)
        ->get(route('employees.employment-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->has('employments.data', 2)
            ->where('employments.data.0.hire_date', '2026-05-01')
            ->where('employments.data.0.tenure_days', 15)
            ->where('employments.data.1.hire_date', '2025-03-01')
            ->where('employments.data.1.tenure_days', 10));

    $this->travelBack();
});

test('tenure sort orders employment rows by tenure span', function (): void {
    $this->travelTo(now()->parse('2026-05-15')->startOfDay());

    config(['hris.default_organization_code' => 'T-EMP-HIST-TEN-SORT']);

    $organization = Organization::factory()->create([
        'code' => 'T-EMP-HIST-TEN-SORT',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id]);

    $longTenure = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2025-01-01',
        'separation_date' => null,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2025-03-01',
        'separation_date' => '2025-03-10',
        'employment_status' => EmployeeEmployment::STATUS_TERMINATED,
        'is_current' => false,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'employee_employment_id' => $longTenure->id,
        'end_date' => null,
    ]);

    $user = employmentHistoryMakeUser();

    $this->actingAs($user)
        ->get(route('employees.employment-history', ['sort' => 'tenure', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->where('filters.sort', 'tenure')
            ->where('filters.direction', 'asc')
            ->where('employments.data.0.tenure_days', 10)
            ->where('employments.data.1.tenure_days', 500));

    $this->actingAs($user)
        ->get(route('employees.employment-history', ['sort' => 'tenure', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/EmploymentHistory')
            ->where('filters.sort', 'tenure')
            ->where('filters.direction', 'desc')
            ->where('employments.data.0.tenure_days', 500)
            ->where('employments.data.1.tenure_days', 10));

    $this->travelBack();
});
