<?php

use App\Enums\EmployeeBirthdayVisibility;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('branch calendar exposes branch-visibility birthdays for the selected branch month grid', function () {
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

    $branchRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);

    Role::query()->create(['code' => Role::CODE_HR_HEAD, 'name' => 'HR Head']);

    $actor = Employee::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['employee_id' => $actor->id]);
    $user->assignRole(Role::CODE_HR_HEAD);

    $visible = Employee::factory()->create([
        'first_name' => 'Show',
        'last_name' => 'BranchBDay',
        'birthdate' => '1990-03-15',
        'birthday_visibility' => EmployeeBirthdayVisibility::Branch,
    ]);

    $hidden = Employee::factory()->create([
        'birthdate' => '1992-03-20',
        'birthday_visibility' => EmployeeBirthdayVisibility::Private,
    ]);

    EmployeeAffiliation::factory()->create([
        'employee_id' => $visible->id,
        'root_unit_id' => $branchRoot->id,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Operations Lead',
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $visible->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-01-01',
        'end_date' => null,
    ]);

    EmployeeAffiliation::factory()->create([
        'employee_id' => $hidden->id,
        'root_unit_id' => $branchRoot->id,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $branchRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $branchRoot->code,
                'name' => $branchRoot->name,
            ],
        ])
        ->get(route('calendar.branch', ['month' => '2026-03']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Branch')
            ->has('branchBirthdays', 1)
            ->where('branchBirthdays.0.eventKind', 'birthday')
            ->where('branchBirthdays.0.employeeId', $visible->id)
            ->where('branchBirthdays.0.title', "Show BranchBDay's birthday")
            ->where('branchBirthdays.0.primaryPositionTitle', 'Operations Lead'));
});

test('team calendar exposes team-visibility birthdays for a selected unit in the month grid', function () {
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

    $branchRoot = OrganizationalUnit::factory()->create([
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
        'parent_id' => $branchRoot->id,
        'code' => 'TAG-A',
        'name' => 'Alpha Team',
        'is_active' => true,
    ]);

    Role::query()->create(['code' => Role::CODE_HR_HEAD, 'name' => 'HR Head']);

    $actor = Employee::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['employee_id' => $actor->id]);
    $user->assignRole(Role::CODE_HR_HEAD);

    $teamVisible = Employee::factory()->create([
        'first_name' => 'Team',
        'last_name' => 'BDay',
        'birthdate' => '1988-03-12',
        'birthday_visibility' => EmployeeBirthdayVisibility::Team,
    ]);

    $branchOnly = Employee::factory()->create([
        'birthdate' => '1991-03-18',
        'birthday_visibility' => EmployeeBirthdayVisibility::Branch,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $teamVisible->id,
        'organizational_unit_id' => $alphaTeam->id,
        'end_date' => null,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $branchOnly->id,
        'organizational_unit_id' => $alphaTeam->id,
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $branchRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $branchRoot->code,
                'name' => $branchRoot->name,
            ],
        ])
        ->get(route('calendar.team', [
            'month' => '2026-03',
            'unit_id' => $alphaTeam->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('teamBirthdays', 1)
            ->where('teamBirthdays.0.eventKind', 'birthday')
            ->where('teamBirthdays.0.employeeId', $teamVisible->id)
            ->where('teamBirthdays.0.title', "Team BDay's birthday"));
});
