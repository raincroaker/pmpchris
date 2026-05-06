<?php

use App\Enums\WorkScheduleClockPattern;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
});

function selectableRootOrganizationalUnitFactory(Organization $organization): OrganizationalUnit
{
    $unitType = UnitType::factory()->create([
        'name' => 'RootType '.uniqid(),
        'can_be_root' => true,
        'is_active' => true,
    ]);

    return OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);
}

test('guests are redirected from about me', function () {
    $this->get(route('employees.about-me'))->assertRedirect(route('login'));
});

test('guests are redirected from employee show', function () {
    $this->get(route('employees.show', ['employee' => 1]))->assertRedirect(route('login'));
});

test('authenticated users without employee linkage receive forbidden when visiting about me', function (): void {
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('employees.about-me'))
        ->assertForbidden();
});

test('authenticated users with employee linkage can visit about me and receive profile props', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-ABTM-ORG',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ABTM-ORG']);

    $root = selectableRootOrganizationalUnitFactory($organization);
    $root->forceFill([
        'name' => 'Eastern Operations',
        'code' => 'EAST-ROOT',
    ])->save();

    $employee = Employee::factory()->create([
        'first_name' => 'Taylor',
        'middle_name' => 'Q',
        'last_name' => 'Nguyen',
        'id_number' => 'EMP-T-555',
        'suffix' => null,
        'sex' => 'female',
        'civil_status' => 'married',
        'nationality' => 'Filipino',
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-06-01',
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
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

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'DEV-3',
        'title' => 'Developer III',
        'is_active' => true,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->get(route('employees.about-me'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/AboutMe')
            ->has('profile')
            ->where('canEditAboutMeHris', false)
            ->where('aboutMeWork', null)
            ->where('profile.display_name', 'Taylor Q Nguyen')
            ->where('profile.id_number', 'EMP-T-555')
            ->where('profile.branch_label', 'Eastern Operations')
            ->where('profile.org_scope_label', 'Branch-scoped')
            ->where('profile.affiliation_history.0.unit', 'Eastern Operations')
            ->where('profile.stats.2.value', 'Developer III'));
});

test('about me picks matching workspace branch when multiple active branch affiliations exist', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-ABTM-MULTI',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ABTM-MULTI']);

    $east = selectableRootOrganizationalUnitFactory($organization);
    $east->forceFill(['name' => 'East Hub', 'code' => 'EAST'])->save();

    $west = selectableRootOrganizationalUnitFactory($organization);
    $west->forceFill(['name' => 'West Hub', 'code' => 'WEST'])->save();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2019-01-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $east->id,
        'is_primary' => true,
        'start_date' => '2019-01-01',
        'end_date' => null,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $west->id,
        'is_primary' => false,
        'start_date' => '2019-01-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $west->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $west->code,
                'name' => (string) $west->name,
            ],
        ])
        ->get(route('employees.about-me'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.branch_label', 'West Hub')
            ->where('canEditAboutMeHris', true));
});

test('about me falls back to primary branch affiliation when session branch root does not match', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-ABTM-FBACK',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ABTM-FBACK']);

    $east = selectableRootOrganizationalUnitFactory($organization);
    $east->forceFill(['name' => 'Alpha Hub', 'code' => 'ALPHA'])->save();

    $west = selectableRootOrganizationalUnitFactory($organization);
    $west->forceFill(['name' => 'Beta Hub', 'code' => 'BETA'])->save();

    $central = selectableRootOrganizationalUnitFactory($organization);
    $central->forceFill(['name' => 'Central Hub', 'code' => 'CENT'])->save();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2018-01-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $east->id,
        'is_primary' => true,
        'start_date' => '2018-01-01',
        'end_date' => null,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $west->id,
        'is_primary' => false,
        'start_date' => '2018-01-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $central->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $central->code,
                'name' => (string) $central->name,
            ],
        ])
        ->get(route('employees.about-me'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('profile.branch_label', 'Alpha Hub'));
});

test('about me treats org-wide active affiliation without branch roots as org-wide hero', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-ABTM-WIDE',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ABTM-WIDE']);

    $sessionRoot = selectableRootOrganizationalUnitFactory($organization);
    $sessionRoot->forceFill(['name' => 'Picker Anchor', 'code' => 'ANCH'])->save();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2021-01-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'is_primary' => true,
        'start_date' => '2021-01-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $sessionRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $sessionRoot->code,
                'name' => (string) $sessionRoot->name,
            ],
        ])
        ->get(route('employees.about-me'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.branch_label', 'Org-wide')
            ->where('profile.org_scope_label', 'Organization-wide')
            ->where('canEditAboutMeHris', true));
});

test('hr head can visit employee show and receive composed profile props', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-SHOW-ORG',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-SHOW-ORG']);

    $root = selectableRootOrganizationalUnitFactory($organization);
    $root->forceFill([
        'name' => 'Eastern Operations',
        'code' => 'EAST-ROOT',
    ])->save();

    $employee = Employee::factory()->create([
        'first_name' => 'Taylor',
        'middle_name' => 'Q',
        'last_name' => 'Nguyen',
        'id_number' => 'EMP-T-555',
        'suffix' => null,
        'sex' => 'female',
        'civil_status' => 'married',
        'nationality' => 'Filipino',
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-06-01',
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
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

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'DEV-3',
        'title' => 'Developer III',
        'is_active' => true,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
    ]);

    $hrUser = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrUser)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $root->code,
                'name' => (string) $root->name,
            ],
        ])
        ->get(route('employees.show', ['employee' => $employee->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Show')
            ->where('employeeId', $employee->id)
            ->where('canEditAboutMeHris', true)
            ->where('aboutMeWork.employment_id', $employment->id)
            ->where('aboutMeWork.employee_id', $employee->id)
            ->where('profile.display_name', 'Taylor Q Nguyen')
            ->where('profile.id_number', 'EMP-T-555')
            ->where('profile.branch_label', 'Eastern Operations')
            ->where('profile.org_scope_label', 'Branch-scoped')
            ->where('profile.affiliation_history.0.unit', 'Eastern Operations')
            ->where('profile.stats.2.value', 'Developer III'));
});

test('employee user cannot visit employee show profile even for own id', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-SHOW-SELF',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-SHOW-SELF']);

    $root = selectableRootOrganizationalUnitFactory($organization);
    $root->forceFill(['name' => 'North Hub', 'code' => 'NORTH'])->save();

    $employee = Employee::factory()->create([
        'first_name' => 'Sam',
        'middle_name' => '',
        'last_name' => 'Rivera',
        'id_number' => 'EMP-SELF-1',
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2021-01-01',
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => '2021-01-01',
        'end_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'CLK-1',
        'title' => 'Clerk',
        'is_active' => true,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2021-01-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->get(route('employees.show', ['employee' => $employee->id]))
        ->assertForbidden();
});

test('employee user cannot visit another employees show profile', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-SHOW-DENY',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-SHOW-DENY']);

    $alice = Employee::factory()->create(['first_name' => 'Alice']);
    $bob = Employee::factory()->create(['first_name' => 'Bob']);

    $employmentAlice = EmployeeEmployment::factory()->create([
        'employee_id' => $alice->id,
        'hire_date' => '2020-01-01',
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    $root = selectableRootOrganizationalUnitFactory($organization);
    EmployeeAffiliation::query()->create([
        'employee_id' => $alice->id,
        'employee_employment_id' => $employmentAlice->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => '2020-01-01',
        'end_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $alice->id,
        'employee_employment_id' => $employmentAlice->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-01-01',
        'end_date' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $bob->id,
    ]);

    $this->actingAs($user)
        ->get(route('employees.show', ['employee' => $alice->id]))
        ->assertForbidden();
});

test('profile schedule uses first two sessions for display time and passes day tokens', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-SCHED-PROF',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-SCHED-PROF']);

    $root = selectableRootOrganizationalUnitFactory($organization);
    $root->forceFill(['name' => 'Sched Hub', 'code' => 'SH'])->save();

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Split + OT',
        'clock_pattern' => WorkScheduleClockPattern::SplitSessions,
        'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
        'segments' => [
            ['label' => 'Session 1', 'time_in' => '08:00', 'time_out' => '12:00', 'is_overnight' => false],
            ['label' => 'Session 2', 'time_in' => '13:00', 'time_out' => '17:00', 'is_overnight' => false],
            ['label' => 'Overtime', 'time_in' => '17:00', 'time_out' => '19:00', 'is_overnight' => false],
        ],
        'time_in' => '08:00',
        'time_out' => '19:00',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'first_name' => 'Pat',
        'last_name' => 'Lee',
        'work_schedule_template_id' => $template->id,
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-06-01',
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
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

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
    ]);

    $hrUser = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrUser)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $root->code,
                'name' => (string) $root->name,
            ],
        ])
        ->get(route('employees.show', ['employee' => $employee->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEditAboutMeHris', true)
            ->where('profile.schedule_label', fn ($label): bool => is_string($label)
                && str_contains($label, '08:00')
                && str_contains($label, '17:00')
                && ! str_contains($label, '08:00–19:00')
                && ! str_contains($label, '08:00-19:00'))
            ->where('profile.schedule_day_tokens', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'])
            ->where('profile.schedule_working_days', 'Mon, Tue, Wed, Thu, Fri, Sat'));
});
