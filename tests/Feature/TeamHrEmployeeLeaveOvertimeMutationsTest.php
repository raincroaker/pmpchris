<?php

use App\Enums\EmployeeHrRecordStatus;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveDay;
use App\Models\EmployeeOvertime;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\OvertimePolicy;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{org: Organization, branch: OrganizationalUnit}
 */
function teamHrMutationScenario(): array
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-HR-MUT',
        'name' => 'Test HR Mutations',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-HR-MUT']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-MUT',
        'name' => 'Mutation test branch',
        'is_active' => true,
    ]);

    return ['org' => $org, 'branch' => $branch];
}

function seedBranchAffiliation(Employee $employee, Organization $org, OrganizationalUnit $branch): void
{
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
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => null,
    ]);
}

function actingHrHeadWithBranchForTeamHrMutations(User $user, OrganizationalUnit $branch): void
{
    test()->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));
}

test('hr head can store update and soft-delete team employee leave', function (): void {
    (new RoleSeeder)->run();
    ['org' => $org, 'branch' => $branch] = teamHrMutationScenario();

    $subject = Employee::factory()->create();
    $approver = Employee::factory()->create();
    seedBranchAffiliation($subject, $org, $branch);
    seedBranchAffiliation($approver, $org, $branch);

    LeavePolicy::factory()->create([
        'organization_id' => $org->id,
        'code' => 'SEEDVL',
        'name' => 'Seed vacation',
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranchForTeamHrMutations($user, $branch);

    $payload = [
        'employee_id' => $subject->id,
        'organizational_unit_id' => null,
        'leave_type_code' => 'SEEDVL',
        'start_date' => '2026-05-10',
        'end_date' => '2026-05-12',
        'is_half_day_start' => false,
        'is_half_day_end' => false,
        'status' => EmployeeHrRecordStatus::Approved->value,
        'submitted_at' => '2026-05-01',
        'decided_at' => '2026-05-02',
        'approver_employee_id' => $approver->id,
        'reason' => 'Convention travel',
    ];

    $this->actingAs($user)
        ->post(route('leave.team.employee-leaves.store'), $payload)
        ->assertRedirect(route('leave.team'));

    $leave = EmployeeLeave::query()->where('employee_id', $subject->id)->firstOrFail();
    expect($leave->start_date->format('Y-m-d'))->toBe('2026-05-10');
    expect(
        EmployeeLeaveDay::query()->where('employee_leave_id', $leave->id)->count(),
    )->toBe(3);

    $this->actingAs($user)
        ->patch(route('leave.team.employee-leaves.update', $leave), array_merge($payload, [
            'end_date' => '2026-05-14',
        ]))
        ->assertRedirect(route('leave.team'));

    $leave->refresh();
    expect($leave->end_date->format('Y-m-d'))->toBe('2026-05-14');
    expect(
        EmployeeLeaveDay::query()->where('employee_leave_id', $leave->id)->count(),
    )->toBe(5);

    $this->actingAs($user)
        ->delete(route('leave.team.employee-leaves.destroy', $leave))
        ->assertRedirect(route('leave.team'));

    $this->assertSoftDeleted('employee_leaves', ['id' => $leave->id]);
});

test('explicit leave_days payload persists only submitted dates when storing team leave', function (): void {
    (new RoleSeeder)->run();
    ['org' => $org, 'branch' => $branch] = teamHrMutationScenario();

    $subject = Employee::factory()->create();
    $approver = Employee::factory()->create();
    seedBranchAffiliation($subject, $org, $branch);
    seedBranchAffiliation($approver, $org, $branch);

    LeavePolicy::factory()->create([
        'organization_id' => $org->id,
        'code' => 'SEEDGAP',
        'name' => 'Seed gap VL',
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranchForTeamHrMutations($user, $branch);

    $payload = [
        'employee_id' => $subject->id,
        'organizational_unit_id' => null,
        'leave_type_code' => 'SEEDGAP',
        'start_date' => '2026-07-07',
        'end_date' => '2026-07-13',
        'is_half_day_start' => false,
        'is_half_day_end' => false,
        'status' => EmployeeHrRecordStatus::Approved->value,
        'submitted_at' => '2026-07-01',
        'decided_at' => '2026-07-02',
        'approver_employee_id' => $approver->id,
        'reason' => null,
        'leave_days' => [
            ['date' => '2026-07-08', 'is_half_day' => false],
            ['date' => '2026-07-11', 'is_half_day' => true],
        ],
    ];

    $this->actingAs($user)
        ->post(route('leave.team.employee-leaves.store'), $payload)
        ->assertRedirect(route('leave.team'));

    /** @var EmployeeLeave $leave */
    $leave = EmployeeLeave::query()->where('employee_id', $subject->id)->firstOrFail();

    expect(EmployeeLeaveDay::query()->where('employee_leave_id', $leave->id)->count())->toBe(2);

    $jul8 = EmployeeLeaveDay::query()
        ->where('employee_leave_id', $leave->id)
        ->whereDate('leave_date', '2026-07-08')
        ->firstOrFail();

    expect($jul8->is_half_day)->toBeFalse();

    $jul11 = EmployeeLeaveDay::query()
        ->where('employee_leave_id', $leave->id)
        ->whereDate('leave_date', '2026-07-11')
        ->firstOrFail();

    expect($jul11->is_half_day)->toBeTrue();
});

test('duplicate leave_days dates are rejected for team employee leave', function (): void {
    (new RoleSeeder)->run();
    ['org' => $org, 'branch' => $branch] = teamHrMutationScenario();

    $subject = Employee::factory()->create();
    $approver = Employee::factory()->create();
    seedBranchAffiliation($subject, $org, $branch);
    seedBranchAffiliation($approver, $org, $branch);

    LeavePolicy::factory()->create([
        'organization_id' => $org->id,
        'code' => 'SEEDDUP',
        'name' => 'Seed dup VL',
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranchForTeamHrMutations($user, $branch);

    $this->actingAs($user)
        ->from(route('leave.team'))
        ->post(route('leave.team.employee-leaves.store'), [
            'employee_id' => $subject->id,
            'organizational_unit_id' => null,
            'leave_type_code' => 'SEEDDUP',
            'start_date' => '2026-07-07',
            'end_date' => '2026-07-09',
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'status' => EmployeeHrRecordStatus::Approved->value,
            'submitted_at' => '2026-07-01',
            'decided_at' => '2026-07-02',
            'approver_employee_id' => $approver->id,
            'reason' => null,
            'leave_days' => [
                ['date' => '2026-07-08', 'is_half_day' => false],
                ['date' => '2026-07-08', 'is_half_day' => false],
            ],
        ])
        ->assertSessionHasErrors('leave_days');
});

test('hr head can store update and soft-delete team employee overtime', function (): void {
    (new RoleSeeder)->run();
    ['org' => $org, 'branch' => $branch] = teamHrMutationScenario();

    $subject = Employee::factory()->create();
    $approver = Employee::factory()->create();
    seedBranchAffiliation($subject, $org, $branch);
    seedBranchAffiliation($approver, $org, $branch);

    OvertimePolicy::factory()->create([
        'organization_id' => $org->id,
        'code' => 'SEEDOT',
        'name' => 'Seed OT',
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranchForTeamHrMutations($user, $branch);

    $payload = [
        'employee_id' => $subject->id,
        'organizational_unit_id' => null,
        'policy_code' => 'SEEDOT',
        'ot_date' => '2026-05-18',
        'hours' => 3,
        'status' => EmployeeHrRecordStatus::Approved->value,
        'submitted_at' => '2026-05-10',
        'decided_at' => '2026-05-11',
        'approver_employee_id' => $approver->id,
        'reason' => 'Month-end close',
    ];

    $this->actingAs($user)
        ->post(route('overtime.team.employee-overtimes.store'), $payload)
        ->assertRedirect(route('overtime.team'));

    $overtime = EmployeeOvertime::query()->where('employee_id', $subject->id)->firstOrFail();
    expect($overtime->hours)->toBe('3.00');

    $this->actingAs($user)
        ->patch(route('overtime.team.employee-overtimes.update', $overtime), array_merge($payload, [
            'hours' => 4.5,
        ]))
        ->assertRedirect(route('overtime.team'));

    $overtime->refresh();
    expect($overtime->hours)->toBe('4.50');

    $this->actingAs($user)
        ->delete(route('overtime.team.employee-overtimes.destroy', $overtime))
        ->assertRedirect(route('overtime.team'));

    $this->assertSoftDeleted('employee_overtimes', ['id' => $overtime->id]);
});

test('team overtime store succeeds without attendance id or work schedule on employee', function (): void {
    (new RoleSeeder)->run();
    ['org' => $org, 'branch' => $branch] = teamHrMutationScenario();

    $subject = Employee::factory()->create([
        'attendance_id' => null,
        'work_schedule_template_id' => null,
    ]);
    $approver = Employee::factory()->create();
    seedBranchAffiliation($subject, $org, $branch);
    seedBranchAffiliation($approver, $org, $branch);

    /** @var OvertimePolicy $overtimePolicy */
    $overtimePolicy = OvertimePolicy::factory()->create([
        'organization_id' => $org->id,
        'code' => 'OT-WO-SCHED',
        'name' => 'OT without schedule prerequisite',
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranchForTeamHrMutations($user, $branch);

    $payload = [
        'employee_id' => $subject->id,
        'organizational_unit_id' => null,
        'policy_code' => 'OT-WO-SCHED',
        'ot_date' => '2026-05-22',
        'hours' => 2,
        'status' => EmployeeHrRecordStatus::Approved->value,
        'submitted_at' => '2026-05-10',
        'decided_at' => '2026-05-11',
        'approver_employee_id' => $approver->id,
        'reason' => 'No attendance or schedule prerequisites for OT filing',
    ];

    $this->actingAs($user)
        ->post(route('overtime.team.employee-overtimes.store'), $payload)
        ->assertRedirect(route('overtime.team'));

    $overtime = EmployeeOvertime::query()
        ->where('employee_id', $subject->id)
        ->where('overtime_policy_id', $overtimePolicy->id)
        ->firstOrFail();

    expect($overtime->hours)->toBe('2.00');
});

test('plain employee cannot post team employee leave', function (): void {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->post(route('leave.team.employee-leaves.store'), [])
        ->assertForbidden();
});

test('plain employee cannot post team employee overtime', function (): void {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->post(route('overtime.team.employee-overtimes.store'), [])
        ->assertForbidden();
});
