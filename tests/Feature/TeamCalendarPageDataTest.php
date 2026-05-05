<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('team calendar exposes selectable units only within the selected branch', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $teamType = UnitType::factory()->create([
        'name' => 'Team',
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $selectedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);
    $otherRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'PAN',
        'name' => 'Panabo Branch',
        'is_active' => true,
    ]);

    $alphaTeam = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-A',
        'name' => 'Alpha Team',
        'is_active' => true,
    ]);
    $betaTeam = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-B',
        'name' => 'Beta Team',
        'is_active' => true,
    ]);
    $otherBranchTeam = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $otherRoot->id,
        'code' => 'PAN-A',
        'name' => 'Panabo Team',
        'is_active' => true,
    ]);

    Role::query()->create(['code' => Role::CODE_HR_HEAD, 'name' => 'HR Head']);

    $employee = Employee::factory()->create();
    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);
    $user->assignRole(Role::CODE_HR_HEAD);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $alphaTeam->id,
        'is_head' => true,
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $otherBranchTeam->id,
        'is_head' => true,
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.team'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('teamCalendarUnits', 4)
            ->where('teamCalendarUnits.0.id', -1 * $organization->id)
            ->where('teamCalendarUnits.0.canCreateEvent', true)
            ->where('teamCalendarUnits.0.isOrganizationScope', true)
            ->where('teamCalendarUnits.1.id', $selectedRoot->id)
            ->where('teamCalendarUnits.1.canCreateEvent', true)
            ->where('teamCalendarUnits.2.id', $alphaTeam->id)
            ->where('teamCalendarUnits.2.canCreateEvent', true)
            ->where('teamCalendarUnits.3.id', $betaTeam->id)
            ->where('teamCalendarUnits.3.canCreateEvent', true)
            ->where('selectedTeamUnitId', -1 * $organization->id)
        );
});

test('employee with active org-level assignment sees organization scope in team calendar selector', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);

    $selectedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);

    Role::query()->create(['code' => Role::CODE_EMPLOYEE, 'name' => 'Employee']);

    $employee = Employee::factory()->create();
    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);
    $user->assignRole(Role::CODE_EMPLOYEE);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organization_id' => $organization->id,
        'organizational_unit_id' => null,
        'is_head' => false,
        'end_date' => null,
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $selectedRoot->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.team'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('teamCalendarUnits', 1)
            ->where('teamCalendarUnits.0.id', -1 * $organization->id)
            ->where('teamCalendarUnits.0.isOrganizationScope', true)
            ->where('teamCalendarUnits.0.canCreateEvent', false)
            ->where('selectedTeamUnitId', -1 * $organization->id)
        );
});

test('hr manager not assigned to current branch sees only units they are actively assigned to', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $teamType = UnitType::factory()->create([
        'name' => 'Team',
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $selectedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);
    $otherRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'PAN',
        'name' => 'Panabo Branch',
        'is_active' => true,
    ]);

    $assignedTeam = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-A',
        'name' => 'Assigned Team',
        'is_active' => true,
    ]);
    $notAssignedTeam = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-B',
        'name' => 'Not Assigned Team',
        'is_active' => true,
    ]);

    Role::query()->create(['code' => Role::CODE_HR_MANAGER, 'name' => 'HR Manager']);

    $employee = Employee::factory()->create();
    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);
    $user->assignRole(Role::CODE_HR_MANAGER);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $assignedTeam->id,
        'is_head' => false,
        'end_date' => null,
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $selectedRoot->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    // Manager assignment is for another branch, not the selected branch.
    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $otherRoot->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.team'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('teamCalendarUnits', 1)
            ->where('teamCalendarUnits.0.id', $assignedTeam->id)
            ->where('teamCalendarUnits.0.canCreateEvent', false)
            ->where('selectedTeamUnitId', $assignedTeam->id)
        );

    expect($assignedTeam->id)->not->toBe($notAssignedTeam->id);
});

test('hr manager assigned to current branch can view all branch units and create including root unit', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $teamType = UnitType::factory()->create([
        'name' => 'Team',
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $selectedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);
    $alphaTeam = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-A',
        'name' => 'Alpha Team',
        'is_active' => true,
    ]);
    $betaTeam = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-B',
        'name' => 'Beta Team',
        'is_active' => true,
    ]);

    Role::query()->create(['code' => Role::CODE_HR_MANAGER, 'name' => 'HR Manager']);

    $employee = Employee::factory()->create();
    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);
    $user->assignRole(Role::CODE_HR_MANAGER);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $alphaTeam->id,
        'is_head' => false,
        'end_date' => null,
    ]);

    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $selectedRoot->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.team'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('teamCalendarUnits', 3)
            ->where('teamCalendarUnits.0.id', $selectedRoot->id)
            ->where('teamCalendarUnits.0.canCreateEvent', true)
            ->where('teamCalendarUnits.1.id', $alphaTeam->id)
            ->where('teamCalendarUnits.1.canCreateEvent', true)
            ->where('teamCalendarUnits.2.id', $betaTeam->id)
            ->where('teamCalendarUnits.2.canCreateEvent', true)
            ->where('selectedTeamUnitId', $selectedRoot->id)
        );
});

test('super admin always sees selected branch root in team calendar selector', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $teamType = UnitType::factory()->create([
        'name' => 'Team',
        'can_be_root' => false,
        'is_active' => true,
    ]);

    $selectedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);
    $team = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-A',
        'name' => 'Alpha Team',
        'is_active' => true,
    ]);

    Role::query()->create(['code' => Role::CODE_SUPER_ADMIN, 'name' => 'Super Administrator']);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_SUPER_ADMIN);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.team'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('teamCalendarUnits', 3)
            ->where('teamCalendarUnits.0.id', -1 * $organization->id)
            ->where('teamCalendarUnits.0.canCreateEvent', true)
            ->where('teamCalendarUnits.0.isOrganizationScope', true)
            ->where('teamCalendarUnits.1.id', $selectedRoot->id)
            ->where('teamCalendarUnits.1.canCreateEvent', true)
            ->where('teamCalendarUnits.2.id', $team->id)
            ->where('teamCalendarUnits.2.canCreateEvent', true)
            ->where('selectedTeamUnitId', -1 * $organization->id)
        );
});
