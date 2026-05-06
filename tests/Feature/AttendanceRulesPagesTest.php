<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

describe('calendar holidays page', function (): void {
    beforeEach(function (): void {
        (new OrganizationalStructureSeeder)->run();
        (new DemoCooperativeSeeder)->run();
        config(['hris.default_organization_code' => 'PMPC']);
    });

    test('authenticated users can visit calendar holidays page', function () {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('calendar.holidays'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calendar/Holidays'));
    });
});

function shiftsSeedOrgWithSelectableBranchRoot(): OrganizationalUnit
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-AR-SHIFTS',
        'name' => 'Test Cooperative AR Shifts',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-AR-SHIFTS']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    return OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-AR-SH',
        'name' => 'AR Shifts Test Branch',
        'is_active' => true,
    ]);
}

/**
 * @return array{0: OrganizationalUnit, 1: OrganizationalUnit}
 */
function shiftsSeedTwoBranchRootsInOrg(): array
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-AR-2BR',
        'name' => 'Test Cooperative AR Two Br',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-AR-2BR']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-AR-A',
        'name' => 'Branch A AR',
        'is_active' => true,
    ]);

    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-AR-B',
        'name' => 'Branch B AR',
        'is_active' => true,
    ]);

    return [$rootA, $rootB];
}

describe('attendance shifts page access', function (): void {
    test('guests are redirected to login for work schedules', function (): void {
        $this->get(route('attendance.shifts'))
            ->assertRedirect(route('login'));
    });

    test('employee role can view work schedules', function (): void {
        (new RoleSeeder)->run();

        /** @var User $user */
        $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

        $this->actingAs($user)
            ->get(route('attendance.shifts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Shifts')
                ->has('workScheduleTemplates'));
    });

    test('hr head may access work schedules after selecting workspace branch', function (): void {
        (new RoleSeeder)->run();
        $branch = shiftsSeedOrgWithSelectableBranchRoot();

        /** @var User $user */
        $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

        $this->actingAs($user)
            ->post(route('branch.store'), [
                'branch_id' => $branch->id,
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->actingAs($user)
            ->get(route('attendance.shifts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Shifts')
                ->has('workScheduleTemplates'));
    });

    test('super admin may access work schedules after selecting workspace branch', function (): void {
        (new RoleSeeder)->run();
        $branch = shiftsSeedOrgWithSelectableBranchRoot();

        /** @var User $user */
        $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

        $this->actingAs($user)
            ->post(route('branch.store'), [
                'branch_id' => $branch->id,
            ]);

        $this->actingAs($user)
            ->get(route('attendance.shifts'))
            ->assertOk();
    });

    test('hr manager may access work schedules when assigned as branch manager for workspace branch', function (): void {
        (new RoleSeeder)->run();
        [$rootA] = shiftsSeedTwoBranchRootsInOrg();

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
            ->get(route('attendance.shifts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Shifts')
                ->has('workScheduleTemplates'));
    });

    test('hr manager can view work schedules without branch manager assignment', function (): void {
        (new RoleSeeder)->run();
        $branch = shiftsSeedOrgWithSelectableBranchRoot();
        $org = Organization::query()->where('code', 'T-AR-SHIFTS')->firstOrFail();

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
            ->get(route('attendance.shifts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Shifts')
                ->has('workScheduleTemplates'));
    });

    test('hr manager can view work schedules when workspace branch is not a managed branch root', function (): void {
        (new RoleSeeder)->run();
        [$rootA, $rootB] = shiftsSeedTwoBranchRootsInOrg();
        $org = Organization::query()->where('code', 'T-AR-2BR')->firstOrFail();

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
            ->get(route('attendance.shifts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Shifts')
                ->has('workScheduleTemplates'));
    });
});
