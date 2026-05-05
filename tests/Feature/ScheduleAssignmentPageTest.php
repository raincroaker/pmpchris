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
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function scheduleAssignmentSeedOrgWithSelectableBranchRoot(): OrganizationalUnit
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-SCHED-ASG',
        'name' => 'Test Cooperative Sched',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-SCHED-ASG']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    return OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-SCHED',
        'name' => 'Sched Test Branch',
        'is_active' => true,
    ]);
}

/**
 * @return array{0: OrganizationalUnit, 1: OrganizationalUnit}
 */
function seedTwoBranchRootsInOrg(): array
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-SCHED-2BR',
        'name' => 'Test Cooperative Two Br',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-SCHED-2BR']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-SA',
        'name' => 'Branch A',
        'is_active' => true,
    ]);

    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-SB',
        'name' => 'Branch B',
        'is_active' => true,
    ]);

    return [$rootA, $rootB];
}

test('guests are redirected to login for schedule assignment', function (): void {
    $this->get(route('attendance.employee-schedules'))
        ->assertRedirect(route('login'));
});

test('legacy attendance schedule assignment url permanently redirects to employee schedules', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get('/attendance/schedule-assignment')
        ->assertStatus(301)
        ->assertRedirect(route('attendance.employee-schedules', absolute: false));
});

test('employee role cannot access schedule assignment', function (): void {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertRedirect(route('dashboard'));
});

test('hr head may access schedule assignment after selecting workspace branch', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment')
            ->has('employees')
            ->has('scheduleTemplateOptions'));
});

test('super admin may access schedule assignment after selecting workspace branch', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertOk();
});

test('hr manager may access schedule assignment when assigned as branch manager for workspace branch', function (): void {
    (new RoleSeeder)->run();
    [$rootA] = seedTwoBranchRootsInOrg();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $rootA->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $rootA->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment')
            ->has('employees')
            ->has('scheduleTemplateOptions'));
});

test('hr manager cannot access schedule assignment without branch manager assignment', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();
    $org = Organization::query()->where('code', 'T-SCHED-ASG')->firstOrFail();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create([
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
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertRedirect(route('dashboard'));
});

test('schedule assignment inertia includes extended filters and row shape', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules', [
            'sort' => 'last_name',
            'direction' => 'desc',
            'per_page' => 15,
            'page' => 1,
            'attendance_id_filter' => 'missing',
            'work_schedule_filter' => 'unassigned',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment')
            ->has('unitFilterOptions')
            ->has('filters', fn (Assert $filters) => $filters
                ->where('search', '')
                ->where('sort', 'last_name')
                ->where('direction', 'desc')
                ->where('per_page', 15)
                ->where('position_id', null)
                ->where('org_scope', null)
                ->where('attendance_id_filter', 'missing')
                ->where('work_schedule_filter', 'unassigned')
                ->where('unit_filter', null)));
});

test('placement filter all sentinel is case insensitive', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules', [
            'unit_filter' => 'ALL',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment')
            ->where('filters.unit_filter', null));
});

test('hr head can use organization placement filter', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules', [
            'unit_filter' => 'organization',
        ]))
        ->assertOk();
});

test('hr manager cannot use organization placement filter', function (): void {
    (new RoleSeeder)->run();
    [$rootA] = seedTwoBranchRootsInOrg();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $rootA->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $rootA->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules', [
            'unit_filter' => 'organization',
        ]))
        ->assertInvalid(['unit_filter']);
});

test('hr manager unit filter outside branch subtree is normalized to all', function (): void {
    (new RoleSeeder)->run();
    [$rootA, $rootB] = seedTwoBranchRootsInOrg();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $rootA->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $rootA->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules', [
            'unit_filter' => (string) $rootB->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment')
            ->where('filters.unit_filter', null));
});

test('placement organization filter restricts to employees with organization-level assignments', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();
    $org = Organization::query()->where('code', 'T-SCHED-ASG')->firstOrFail();

    $employmentUnit = EmployeeEmployment::factory()->create([
        'is_current' => true,
    ]);

    $employmentOrg = EmployeeEmployment::factory()->create([
        'is_current' => true,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employmentUnit->employee_id,
        'employee_employment_id' => $employmentUnit->id,
        'organization_id' => $org->id,
        'root_unit_id' => $branch->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employmentOrg->employee_id,
        'employee_employment_id' => $employmentOrg->id,
        'organization_id' => $org->id,
        'root_unit_id' => $branch->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employmentUnit->employee_id,
        'employee_employment_id' => $employmentUnit->id,
        'organization_id' => null,
        'organizational_unit_id' => $branch->id,
        'is_primary' => true,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employmentOrg->employee_id,
        'employee_employment_id' => $employmentOrg->id,
        'organization_id' => $org->id,
        'organizational_unit_id' => null,
        'is_primary' => true,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules', [
            'unit_filter' => 'organization',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment')
            ->where('employees.total', 1)
            ->where('employees.data.0.id', $employmentOrg->employee_id));
});

test('hr head may filter by organizational unit placement', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();
    $org = Organization::query()->where('code', 'T-SCHED-ASG')->firstOrFail();

    $employment = EmployeeEmployment::factory()->create([
        'is_current' => true,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employment->employee_id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $org->id,
        'root_unit_id' => $branch->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $employment->employee_id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $branch->id,
        'is_primary' => true,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules', [
            'unit_filter' => (string) $branch->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment')
            ->where('employees.total', 1)
            ->where('employees.data.0.id', $employment->employee_id));
});

test('hr manager cannot access schedule assignment when workspace branch is not a managed branch root', function (): void {
    (new RoleSeeder)->run();
    [$rootA, $rootB] = seedTwoBranchRootsInOrg();
    $org = Organization::query()->where('code', 'T-SCHED-2BR')->firstOrFail();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create([
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

    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $rootB->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $rootA->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertRedirect(route('dashboard'));
});

test('schedule assignment eager load resolves unit type and color when organizational unit selects unit_type_id', function (): void {
    (new RoleSeeder)->run();
    $branch = scheduleAssignmentSeedOrgWithSelectableBranchRoot();
    $org = Organization::query()->where('code', 'T-SCHED-ASG')->firstOrFail();

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
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

    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $branch->id,
        'is_primary' => true,
        'end_date' => null,
    ]);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $today = now()->toDateString();

    /** @var Employee $loaded */
    $loaded = Employee::query()
        ->whereKey($employee->id)
        ->with([
            'assignments' => function ($q) use ($today): void {
                $q->whereNull('deleted_at')
                    ->where(function ($q2) use ($today): void {
                        $q2->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    })
                    ->with([
                        'organizationalUnit:id,code,name,unit_type_id',
                        'organizationalUnit.unitType:id,name,color',
                        'organization:id,code,name',
                    ])
                    ->orderByDesc('is_primary')
                    ->orderBy('id');
            },
        ])
        ->firstOrFail();

    $assignment = $loaded->assignments->first();

    expect($assignment)->not()->toBeNull();

    /** @phpstan-ignore property.nonObject */
    $unitType = $assignment->organizationalUnit->unitType;
    expect($unitType)->not()->toBeNull()
        ->and((string) $unitType->name)->toBe('Branch')
        ->and((string) $unitType->color)->toBe((string) $branchType->color);
});
