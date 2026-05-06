<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkScheduleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.default_organization_code' => 'PMPC']);
    config(['hris.branch_picker_enabled' => false]);
    $this->seed(RoleSeeder::class);
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    $this->seed(WorkScheduleTemplatesSeeder::class);
});

test('users without schedule assignment access cannot open schedule assignment page', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertRedirect(route('dashboard'));
});

test('hr head can open schedule assignment page', function () {
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->get(route('attendance.employee-schedules'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/ScheduleAssignment'));
});

test('hr head can assign work schedule template to employee', function () {
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $template = WorkScheduleTemplate::query()
        ->where('organization_id', $organization->id)
        ->where('is_active', true)
        ->orderBy('id')
        ->skip(1)
        ->firstOrFail();
    $position = Position::query()->where('organization_id', $organization->id)->firstOrFail();
    $employee = Employee::factory()->create();
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);
    $root = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('employees.work-schedule-template.update', $employee), [
            'work_schedule_template_id' => $template->id,
        ])
        ->assertRedirect();

    expect((int) $employee->fresh()->work_schedule_template_id)->toBe((int) $template->id);
});

test('hr head can patch employee attendance id with schedule template', function () {
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $template = WorkScheduleTemplate::query()
        ->where('organization_id', $organization->id)
        ->where('is_active', true)
        ->orderBy('id')
        ->firstOrFail();
    $position = Position::query()->where('organization_id', $organization->id)->firstOrFail();
    $employee = Employee::factory()->create();
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);
    $root = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('employees.work-schedule-template.update', $employee), [
            'work_schedule_template_id' => $template->id,
            'attendance_id' => 'BIO-12345',
        ])
        ->assertRedirect();

    $fresh = $employee->fresh();
    expect((string) $fresh->attendance_id)->toBe('BIO-12345')
        ->and((int) $fresh->work_schedule_template_id)->toBe((int) $template->id);
});

test('users without schedule assignment access cannot patch employee template', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $position = Position::query()->where('organization_id', $organization->id)->firstOrFail();
    $employee = Employee::factory()->create();
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('employees.work-schedule-template.update', $employee), [
            'work_schedule_template_id' => 1,
        ])
        ->assertRedirect(route('dashboard'));
});

test('hr head cannot assign inactive work schedule template to employee who does not have it', function () {
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $position = Position::query()->where('organization_id', $organization->id)->firstOrFail();
    $employee = Employee::factory()->create([
        'work_schedule_template_id' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    /** @var WorkScheduleTemplate $inactive */
    $inactive = WorkScheduleTemplate::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Inactive For Validation Test',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->from(route('attendance.employee-schedules'))
        ->patch(route('employees.work-schedule-template.update', $employee), [
            'work_schedule_template_id' => $inactive->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('work_schedule_template_id');
});

test('hr head may submit patch when employee already holds an inactive template', function () {
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();
    $position = Position::query()->where('organization_id', $organization->id)->firstOrFail();

    /** @var WorkScheduleTemplate $inactive */
    $inactive = WorkScheduleTemplate::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Locked Inactive Template',
        'is_active' => false,
    ]);

    $employee = Employee::factory()->create([
        'work_schedule_template_id' => $inactive->id,
        'attendance_id' => null,
    ]);
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'end_date' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('employees.work-schedule-template.update', $employee), [
            'work_schedule_template_id' => $inactive->id,
            'attendance_id' => 'BIO-STALE',
        ])
        ->assertRedirect();

    $fresh = $employee->fresh();
    expect((int) $fresh->work_schedule_template_id)->toBe((int) $inactive->id)
        ->and((string) ($fresh->attendance_id ?? ''))->toBe('BIO-STALE');
});
