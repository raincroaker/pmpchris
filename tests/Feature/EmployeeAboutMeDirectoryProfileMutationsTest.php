<?php

declare(strict_types=1);

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeContact;
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

function bootstrapDirectoryVisibleEmployeeAboutMe(Organization $organization): Employee
{
    $employee = Employee::factory()->create([
        'first_name' => 'Visible',
        'last_name' => 'Person',
        'id_number' => 'EMP-ABOUT-ME-VIS',
    ]);

    EmployeeEmployment::factory()->for($employee)->create([
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'hire_date' => '2019-01-01',
        'separation_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ABOUT-ME-POS',
        'title' => 'Staff',
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
        'is_primary' => true,
    ]);

    EmployeeContact::factory()->count(2)->create([
        'employee_id' => $employee->id,
        'category' => 'personal',
        'type' => 'mobile',
        'is_primary' => false,
    ]);

    EmployeeContact::query()
        ->where('employee_id', $employee->id)
        ->where('category', 'personal')
        ->update(['is_primary' => false]);

    $firstPersonal = EmployeeContact::query()
        ->where('employee_id', $employee->id)
        ->where('category', 'personal')
        ->orderBy('id')
        ->firstOrFail();
    $firstPersonal->update(['is_primary' => true]);

    EmployeeContact::factory()->emergency()->create([
        'employee_id' => $employee->id,
        'is_primary' => true,
    ]);

    return Employee::query()->findOrFail($employee->getKey());
}

function bootstrapInvisibleDirectoryEmployeeAboutMe(): Employee
{
    $employment = EmployeeEmployment::factory()->create([
        'hire_date' => '2019-06-01',
    ]);

    return Employee::query()->findOrFail($employment->employee_id);
}

function directoryAboutMeSelectableRoot(Organization $organization): OrganizationalUnit
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
        'code' => 'ROOT-ABM-'.$organization->code,
        'name' => 'Test root',
    ]);
}

/** HR Manager with BranchManager + single affiliation defining workspace branch. */
function directoryAboutMeHrManagerForRoot(
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

test('guests cannot patch employee about-me basics', function (): void {
    $employee = Employee::factory()->create();

    $this->patch(route('employees.about-me.basics', ['employee' => $employee->id]), [
        'first_name' => 'X',
        'last_name' => 'Y',
        'id_number' => $employee->id_number,
    ])->assertRedirect(route('login'));
});

test('plain employee role cannot patch about-me basics', function (): void {
    $employee = Employee::factory()->create();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->patch(route('employees.about-me.basics', ['employee' => $employee->id]), [
            'first_name' => 'Hack',
            'middle_name' => null,
            'last_name' => 'Attempt',
            'id_number' => $employee->id_number,
        ])
        ->assertForbidden();
});

test('hr head cannot patch basics when employee is outside directory visibility', function (): void {
    config(['hris.default_organization_code' => 'T-ABOUT-ME-VIS']);

    Organization::factory()->create([
        'code' => 'T-ABOUT-ME-VIS',
        'is_active' => true,
    ]);

    $employee = bootstrapInvisibleDirectoryEmployeeAboutMe();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->patch(route('employees.about-me.basics', ['employee' => $employee->id]), [
            'first_name' => 'No',
            'middle_name' => null,
            'last_name' => 'Access',
            'id_number' => $employee->id_number,
        ])
        ->assertForbidden();
});

test('hr head can patch basics for directory visible employee', function (): void {
    config(['hris.default_organization_code' => 'T-ABOUT-ME-OK']);

    $organization = Organization::factory()->create([
        'code' => 'T-ABOUT-ME-OK',
        'is_active' => true,
    ]);

    $employee = bootstrapDirectoryVisibleEmployeeAboutMe($organization);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->patch(route('employees.about-me.basics', ['employee' => $employee->id]), [
            'first_name' => 'Updated',
            'middle_name' => 'Q',
            'last_name' => 'Nametag',
            'id_number' => $employee->id_number,
            'attendance_id' => 'ATT-NEW-1',
        ])
        ->assertRedirect();

    $employee->refresh();

    expect($employee->first_name)->toBe('Updated')
        ->and((string) $employee->middle_name)->toBe('Q')
        ->and($employee->last_name)->toBe('Nametag')
        ->and((string) $employee->attendance_id)->toBe('ATT-NEW-1');
});

test('hr manager without managed workspace branch is forbidden from about-me basics patch', function (): void {
    config(['hris.default_organization_code' => 'T-ABOUT-ME-MGR-GATE']);

    $organization = Organization::factory()->create([
        'code' => 'T-ABOUT-ME-MGR-GATE',
        'is_active' => true,
    ]);

    $employee = bootstrapDirectoryVisibleEmployeeAboutMe($organization);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patch(route('employees.about-me.basics', ['employee' => $employee->id]), [
            'first_name' => 'No',
            'middle_name' => null,
            'last_name' => 'Route',
            'id_number' => $employee->id_number,
        ])
        ->assertForbidden();
});

test('hr manager can patch basics for directory visible employee when workspace branch is managed', function (): void {
    config(['hris.default_organization_code' => 'T-ABOUT-ME-MGR-OK']);

    $organization = Organization::factory()->create([
        'code' => 'T-ABOUT-ME-MGR-OK',
        'is_active' => true,
    ]);

    $root = directoryAboutMeSelectableRoot($organization);
    $employee = bootstrapDirectoryVisibleEmployeeAboutMe($organization);

    $user = directoryAboutMeHrManagerForRoot($organization, $root);

    $this->actingAs($user)
        ->patch(route('employees.about-me.basics', ['employee' => $employee->id]), [
            'first_name' => 'MgrUpd',
            'middle_name' => null,
            'last_name' => 'Case',
            'id_number' => $employee->id_number,
            'attendance_id' => 'ATT-MGR-1',
        ])
        ->assertRedirect();

    $employee->refresh();

    expect($employee->first_name)->toBe('MgrUpd')
        ->and($employee->last_name)->toBe('Case')
        ->and((string) $employee->attendance_id)->toBe('ATT-MGR-1');
});

test('hr head can sync contacts for directory visible employee', function (): void {
    config(['hris.default_organization_code' => 'T-ABOUT-ME-CONTACTS']);

    $organization = Organization::factory()->create([
        'code' => 'T-ABOUT-ME-CONTACTS',
        'is_active' => true,
    ]);

    $employee = bootstrapDirectoryVisibleEmployeeAboutMe($organization);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $existingEmergency = EmployeeContact::query()
        ->where('employee_id', $employee->id)
        ->where('category', 'emergency')
        ->first();

    expect($existingEmergency)->not()->toBeNull();

    $this->actingAs($user)
        ->patch(route('employees.about-me.contacts', ['employee' => $employee->id]), [
            'personal' => [[
                'channel_label' => 'Mobile',
                'contact_number' => '+631111222333',
                'email' => 'me@company.test',
                'is_primary' => true,
            ]],
            'emergency' => [[
                'channel_label' => 'Mobile',
                'contact_person' => 'Taylor Kim',
                'relationship' => 'Spouse',
                'contact_number' => '+634445556666',
                'email' => null,
                'is_primary' => true,
            ]],
        ])
        ->assertRedirect();

    $personal = EmployeeContact::query()
        ->where('employee_id', $employee->id)
        ->where('category', 'personal')
        ->whereNull('deleted_at')
        ->get();

    expect($personal)->toHaveCount(1)
        ->and((string) $personal->first()->contact_number)->toBe('+631111222333');

    $emergency = EmployeeContact::query()
        ->where('employee_id', $employee->id)
        ->where('category', 'emergency')
        ->whereNull('deleted_at')
        ->get();

    expect($emergency)->toHaveCount(1)
        ->and((string) ($emergency->first()->contact_person ?? ''))->toBe('Taylor Kim');
});
