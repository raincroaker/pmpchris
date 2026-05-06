<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    config(['hris.branch_picker_enabled' => false]);
});

/**
 * User factory default password is `password`.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function employmentDatesAuthPayload(array $data = []): array
{
    return array_merge([
        'current_password' => 'password',
        'current_password_confirmation' => 'password',
    ], $data);
}

/** Current position-linked employee row (directory-visible via non-branch scope). */
function updateEmploymentDatesBootstrapVisibleEmployee(Organization $organization): Employee
{
    $employee = Employee::factory()->create([
        'first_name' => 'Visible',
        'last_name' => 'Person',
        'id_number' => 'EMP-UPD-DATES',
    ]);

    EmployeeEmployment::factory()->for($employee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'hire_date' => '2019-01-01',
        'separation_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'UPD-DATES-POS',
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

function updateEmploymentDatesMakeHrUser(): User
{
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    return $user;
}

/** Selectable root for default org tests (branch picker disabled). */
function updateEmploymentDatesMakeSelectableRoot(Organization $organization): OrganizationalUnit
{
    $unitType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);

    return OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
        'code' => 'ROOT-UPD-'.$organization->code,
        'name' => 'Test root',
    ]);
}

/**
 * HR Manager with BranchManager assignment and sole affiliation defining workspace branch (matches {@see EmployeeTeamHrPagesAccess}).
 */
function updateEmploymentDatesMakeHrManagerAuthenticatedForOrgRoot(
    Organization $organization,
    OrganizationalUnit $root,
): User {
    $managerEmployee = Employee::factory()->create();
    /** @var User $managerUser */
    $managerUser = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create([
        'employee_id' => $managerEmployee->id,
    ]);

    $managerEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $managerEmployee->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $managerEmployee->id,
        'employee_employment_id' => $managerEmployment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    BranchManager::factory()->create([
        'user_id' => $managerUser->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
    ]);

    return $managerUser;
}

test('guests cannot patch employment dates', function (): void {
    $employment = EmployeeEmployment::factory()->create();

    $this->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), [
        'hire_date' => '2020-01-01',
    ])->assertRedirect(route('login'));
});

test('employee role cannot patch another users employment dates', function (): void {
    $employment = EmployeeEmployment::factory()->create();
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), [
            'hire_date' => '2020-01-01',
        ])
        ->assertForbidden();
});

test('employee role cannot patch a different linked employees employment', function (): void {
    $employment = EmployeeEmployment::factory()->create([
        'hire_date' => '2020-01-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => Employee::factory(),
    ]);

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2020-06-02',
        ]))
        ->assertForbidden();
});

test('employee role cannot patch own current employment hire date', function (): void {
    config(['hris.default_organization_code' => 'T-SELF-HIRE']);

    $organization = Organization::factory()->create([
        'code' => 'T-SELF-HIRE',
        'is_active' => true,
    ]);

    $unitType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-06-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'SELF-POS',
        'is_active' => true,
    ]);

    EmployeePosition::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
        'notes' => null,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2020-05-15',
        ]))
        ->assertForbidden();

    expect($employment->fresh()->hire_date->toDateString())->toBe('2020-06-01')
        ->and($employment->fresh()->separation_date)->toBeNull();
});

test('employee role cannot submit separation draft for active self employment', function (): void {
    config(['hris.default_organization_code' => 'T-SELF-SEP']);

    $organization = Organization::factory()->create([
        'code' => 'T-SELF-SEP',
        'is_active' => true,
    ]);

    $unitType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-06-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'SELF-SEP-POS',
        'is_active' => true,
    ]);

    EmployeePosition::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
        'notes' => null,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2020-06-01',
            'separation_date' => '2026-03-01',
            'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        ]))
        ->assertForbidden();

    expect($employment->fresh()->employment_status)->toBe(EmployeeEmployment::STATUS_ACTIVE)
        ->and($employment->fresh()->separation_date)->toBeNull();
});

test('hr cannot patch employment dates when employment employee is outside directory visibility', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-VIS']);

    Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-VIS',
        'is_active' => true,
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'hire_date' => '2019-06-01',
    ]);

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), [
            'hire_date' => '2019-06-02',
        ])
        ->assertForbidden();
});

test('hr head can patch hire date on active employment', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-ACT']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-ACT',
        'is_active' => true,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);
    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update(['start_date' => '2019-01-15']);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-10',
        ]))
        ->assertRedirect();

    expect($employment->fresh()->hire_date->toDateString())->toBe('2019-01-10')
        ->and($employment->fresh()->separation_date)->toBeNull();
});

test('hr manager can patch hire date on active employment', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-MGR']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-MGR',
        'is_active' => true,
    ]);

    $root = updateEmploymentDatesMakeSelectableRoot($organization);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);
    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update(['start_date' => '2019-01-15']);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    $user = updateEmploymentDatesMakeHrManagerAuthenticatedForOrgRoot($organization, $root);

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-12',
        ]))
        ->assertRedirect();

    expect($employment->fresh()->hire_date->toDateString())->toBe('2019-01-12')
        ->and($employment->fresh()->separation_date)->toBeNull();
});

test('hr manager without managed workspace branch is redirected away from employment dates patch', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-MGR-GATE']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-MGR-GATE',
        'is_active' => true,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-12',
        ]))
        ->assertRedirect(route('dashboard'));
});

test('active employment rejects separation payload without employment status', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-SEP']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-SEP',
        'is_active' => true,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);
    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update(['start_date' => '2019-01-15']);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->where('employee_employment_id', $employment->id)
        ->update([
            'start_date' => '2019-01-15',
            'end_date' => '2025-06-01',
        ]);

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-15',
            'separation_date' => '2025-06-01',
        ]))
        ->assertSessionHasErrors('employment_status');
});

test('active employment can record separation when assignments are ended', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-RECORD']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-RECORD',
        'is_active' => true,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update([
            'employee_employment_id' => $employment->id,
            'start_date' => '2019-01-15',
            'end_date' => '2025-06-30',
        ]);

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-15',
            'separation_date' => '2025-06-30',
            'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
            'separation_reason' => 'Career move',
            'notes' => 'Clean exit checklist completed.',
        ]))
        ->assertRedirect();

    $employment->refresh();
    expect($employment->employment_status)->toBe(EmployeeEmployment::STATUS_RESIGNED)
        ->and($employment->separation_date->toDateString())->toBe('2025-06-30')
        ->and((bool) $employment->is_current)->toBeFalse()
        ->and($employment->separation_reason)->toBe('Career move')
        ->and($employment->notes)->toBe('Clean exit checklist completed.');
});

test('hr manager cannot record separation on active employment', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-MGR-SEP']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-MGR-SEP',
        'is_active' => true,
    ]);

    $root = updateEmploymentDatesMakeSelectableRoot($organization);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update([
            'employee_employment_id' => $employment->id,
            'start_date' => '2019-01-15',
            'end_date' => '2025-06-30',
        ]);

    $user = updateEmploymentDatesMakeHrManagerAuthenticatedForOrgRoot($organization, $root);

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-15',
            'separation_date' => '2025-06-30',
            'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        ]))
        ->assertSessionHasErrors('separation_date');

    $employment->refresh();
    expect($employment->employment_status)->toBe(EmployeeEmployment::STATUS_ACTIVE)
        ->and($employment->separation_date)->toBeNull();
});

test('active employment rejects separation when org chart assignment is open-ended', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-ORGCH']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-ORGCH',
        'is_active' => true,
    ]);

    $unit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update([
            'employee_employment_id' => $employment->id,
            'start_date' => '2019-01-15',
            'end_date' => '2025-06-30',
        ]);

    EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'is_primary' => false,
        'is_head' => false,
        'start_date' => '2019-01-15',
        'end_date' => null,
    ]);

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-15',
            'separation_date' => '2025-06-30',
            'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        ]))
        ->assertSessionHasErrors('separation_date');
});

test('hire date cannot be after earliest position start', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-HIRE']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-HIRE',
        'is_active' => true,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);
    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update(['start_date' => '2019-01-15']);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-02-01',
        ]))
        ->assertSessionHasErrors('hire_date');
});

test('separated employment cannot be edited via patch', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-INACT']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-INACT',
        'is_active' => true,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);

    $inactive = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2018-01-01',
        'separation_date' => '2021-06-01',
        'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        'is_current' => false,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $inactive->id,
        'position_id' => $position->id,
        'start_date' => '2018-01-01',
        'end_date' => '2021-06-01',
        'is_primary' => true,
    ]);

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $inactive->id]), employmentDatesAuthPayload([
            'hire_date' => '2018-01-01',
            'separation_date' => '2021-03-01',
            'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
        ]))
        ->assertSessionHasErrors('employment');
});

test('patch rejects incorrect current password', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-BADPW']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-BADPW',
        'is_active' => true,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update(['start_date' => '2019-01-15']);

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), employmentDatesAuthPayload([
            'hire_date' => '2019-01-15',
            'current_password' => 'wrong-password',
            'current_password_confirmation' => 'wrong-password',
        ]))
        ->assertSessionHasErrors('current_password');
});

test('patch rejects mismatched password confirmation', function (): void {
    config(['hris.default_organization_code' => 'T-UPDATE-EMP-MISM']);

    $organization = Organization::factory()->create([
        'code' => 'T-UPDATE-EMP-MISM',
        'is_active' => true,
    ]);

    $employee = updateEmploymentDatesBootstrapVisibleEmployee($organization);

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employee->id)
        ->where('is_current', true)
        ->firstOrFail();

    $employment->hire_date = '2019-01-15';
    $employment->save();

    EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->update(['start_date' => '2019-01-15']);

    $user = updateEmploymentDatesMakeHrUser();

    $this->actingAs($user)
        ->patch(route('employees.employments.update-dates', ['employment' => $employment->id]), [
            'hire_date' => '2019-01-15',
            'current_password' => 'password',
            'current_password_confirmation' => 'not-matching',
        ])
        ->assertSessionHasErrors('current_password_confirmation');
});
