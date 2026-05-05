<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use App\Enums\WorkScheduleClockPattern;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{org: Organization, branch: OrganizationalUnit, template: WorkScheduleTemplate, employee: Employee}
 */
function teamAttendanceMutationScenario(): array
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ATT-MUT',
        'name' => 'Test attendance mutations org',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-ATT-MUT']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-ATT',
        'name' => 'Attendance mutate branch',
        'is_active' => true,
    ]);

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Attendance mutation template',
        'time_in' => '08:00',
        'time_out' => '17:00',
        'unpaid_break_minutes' => 60,
        'grace_late_arrival_minutes' => 15,
        'is_overnight' => false,
    ]);

    $employee = Employee::factory()->create([
        'attendance_id' => 'ATT-MUT-1',
        'work_schedule_template_id' => $template->id,
    ]);

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

    return ['org' => $org, 'branch' => $branch, 'template' => $template, 'employee' => $employee];
}

function actingHrHeadWithBranch(User $user, OrganizationalUnit $branch): void
{
    test()->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));
}

test('hr head can store update and delete team attendance day', function (): void {
    (new RoleSeeder)->run();
    ['branch' => $branch, 'template' => $template, 'employee' => $employee] = teamAttendanceMutationScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranch($user, $branch);

    $workDate = '2026-05-18';

    $segmentPayload = [
        [
            'label' => 'Shift',
            'scheduled_in' => '08:00',
            'scheduled_out' => '17:00',
            'actual_in' => '08:05',
            'actual_out' => '17:02',
        ],
    ];

    $storePayload = [
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_date' => $workDate,
        'work_schedule_template_id' => $template->id,
        'segments' => $segmentPayload,
        'variance_label' => null,
    ];

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), $storePayload)
        ->assertRedirect(route('attendance.team'));

    $day = EmployeeAttendanceDay::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', $workDate)
        ->firstOrFail();
    expect((float) $day->net_hours)->toBeGreaterThan(0);

    $this->actingAs($user)
        ->patch(route('attendance.team.attendance-days.update', $day), array_merge($storePayload, [
            'segments' => [
                [
                    'label' => 'Shift',
                    'scheduled_in' => '08:00',
                    'scheduled_out' => '17:00',
                    'actual_in' => '08:06',
                    'actual_out' => '17:01',
                ],
            ],
        ]))
        ->assertRedirect(route('attendance.team'));

    $day->refresh();
    expect($day->last_modified_source->value)->toBe('manual');

    $this->actingAs($user)
        ->delete(route('attendance.team.attendance-days.destroy', $day))
        ->assertRedirect(route('attendance.team'));

    expect(EmployeeAttendanceDay::query()->whereKey($day->id)->withTrashed()->first()?->deleted_at)->not->toBeNull();
});

test('plain employee cannot mutate team attendance days', function (): void {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    ['branch' => $branch, 'template' => $template, 'employee' => $employee] = teamAttendanceMutationScenario();

    $day = EmployeeAttendanceDay::factory()->create([
        'organization_id' => $branch->organization_id,
        'employee_id' => $employee->id,
        'work_schedule_template_id' => $template->id,
        'organizational_unit_id' => $branch->id,
        'work_date' => '2026-06-01',
    ]);

    $payload = [
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_date' => '2026-06-02',
        'work_schedule_template_id' => $template->id,
        'segments' => [
            ['label' => 'Shift', 'scheduled_in' => '08:00', 'scheduled_out' => '17:00', 'actual_in' => null, 'actual_out' => null],
        ],
    ];

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), $payload)
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('attendance.team.attendance-days.update', $day), array_merge($payload, ['work_date' => '2026-06-01']))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('attendance.team.attendance-days.destroy', $day))
        ->assertForbidden();
});

test('cannot store duplicate employee attendance day on same calendar date', function (): void {
    (new RoleSeeder)->run();
    ['branch' => $branch, 'template' => $template, 'employee' => $employee] = teamAttendanceMutationScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranch($user, $branch);

    $workDate = '2026-05-20';
    $segmentPayload = [
        ['label' => 'Shift', 'scheduled_in' => '08:00', 'scheduled_out' => '17:00', 'actual_in' => null, 'actual_out' => null],
    ];
    $payload = [
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_date' => $workDate,
        'work_schedule_template_id' => $template->id,
        'segments' => $segmentPayload,
    ];

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), $payload)
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), $payload)
        ->assertSessionHasErrors('work_date');
});

test('cannot store team attendance when scheduled times drift from assigned template', function (): void {
    (new RoleSeeder)->run();
    ['branch' => $branch, 'template' => $template, 'employee' => $employee] = teamAttendanceMutationScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranch($user, $branch);

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), [
            'employee_id' => $employee->id,
            'organizational_unit_id' => $branch->id,
            'work_date' => '2026-05-21',
            'work_schedule_template_id' => $template->id,
            'segments' => [
                [
                    'label' => 'Shift',
                    'scheduled_in' => '08:30',
                    'scheduled_out' => '17:00',
                    'actual_in' => null,
                    'actual_out' => null,
                ],
            ],
        ])
        ->assertSessionHasErrors('segments');

    expect(EmployeeAttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-05-21')->exists())->toBeFalse();
});

test('team attendance accepts normalized times that equal the template windows', function (): void {
    (new RoleSeeder)->run();
    ['branch' => $branch, 'template' => $template, 'employee' => $employee] = teamAttendanceMutationScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranch($user, $branch);

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), [
            'employee_id' => $employee->id,
            'organizational_unit_id' => $branch->id,
            'work_date' => '2026-05-22',
            'work_schedule_template_id' => $template->id,
            'segments' => [
                [
                    'label' => 'Shift',
                    'scheduled_in' => '8:00',
                    'scheduled_out' => '17:00',
                    'actual_in' => null,
                    'actual_out' => null,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(EmployeeAttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-05-22')->exists())->toBeTrue();
});

test('split template team attendance requires exactly two segments matching template windows', function (): void {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-ATT-SPL',
        'name' => 'Split attendance org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ATT-SPL']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-SPL',
        'name' => 'Split branch',
        'is_active' => true,
    ]);

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $org->id,
        'clock_pattern' => WorkScheduleClockPattern::SplitSessions,
        'segments' => [
            ['label' => 'Session 1', 'time_in' => '09:00', 'time_out' => '13:00', 'is_overnight' => false],
            ['label' => 'Session 2', 'time_in' => '14:00', 'time_out' => '18:00', 'is_overnight' => false],
        ],
        'time_in' => '09:00',
        'time_out' => '18:00',
        'unpaid_break_minutes' => 60,
        'grace_late_arrival_minutes' => 10,
    ]);

    $employee = Employee::factory()->create([
        'attendance_id' => 'ATT-SPLIT-1',
        'work_schedule_template_id' => $template->id,
    ]);

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

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    actingHrHeadWithBranch($user, $branch);

    $threeSegPayload = [
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_date' => '2026-06-15',
        'work_schedule_template_id' => $template->id,
        'segments' => [
            ['label' => 'S1', 'scheduled_in' => '09:00', 'scheduled_out' => '13:00', 'actual_in' => null, 'actual_out' => null],
            ['label' => 'S2', 'scheduled_in' => '14:00', 'scheduled_out' => '18:00', 'actual_in' => null, 'actual_out' => null],
            ['label' => 'S3', 'scheduled_in' => '19:00', 'scheduled_out' => '20:00', 'actual_in' => null, 'actual_out' => null],
        ],
    ];

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), $threeSegPayload)
        ->assertSessionHasErrors('segments');

    $twoSegBadTime = [
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_date' => '2026-06-16',
        'work_schedule_template_id' => $template->id,
        'segments' => [
            ['label' => 'S1', 'scheduled_in' => '09:00', 'scheduled_out' => '12:59', 'actual_in' => null, 'actual_out' => null],
            ['label' => 'S2', 'scheduled_in' => '14:00', 'scheduled_out' => '18:00', 'actual_in' => null, 'actual_out' => null],
        ],
    ];

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), $twoSegBadTime)
        ->assertSessionHasErrors('segments');

    $twoSegGood = [
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_date' => '2026-06-17',
        'work_schedule_template_id' => $template->id,
        'segments' => [
            ['label' => 'Session 1', 'scheduled_in' => '09:00', 'scheduled_out' => '13:00', 'actual_in' => '09:01', 'actual_out' => '13:00'],
            ['label' => 'Session 2', 'scheduled_in' => '14:00', 'scheduled_out' => '18:00', 'actual_in' => '13:58', 'actual_out' => '18:00'],
        ],
    ];

    $this->actingAs($user)
        ->post(route('attendance.team.attendance-days.store'), $twoSegGood)
        ->assertSessionHasNoErrors();

    expect(EmployeeAttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-06-17')->exists())->toBeTrue();
});
