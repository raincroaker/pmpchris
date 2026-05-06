<?php

use App\Enums\EmployeeHrRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveDay;
use App\Models\EmployeeOvertime;
use App\Models\HolidayType;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationHoliday;
use App\Models\OvertimePolicy;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use App\Services\EmployeeLeaveDaysSyncService;
use Carbon\Carbon;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedTeamHrLookupScenario(): array
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-TEAM-HR-LU',
        'name' => 'Team HR Lookup Org',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-TEAM-HR-LU']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $departmentType = UnitType::query()->where('name', 'Department')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-LU',
        'name' => 'Lookup Branch',
        'is_active' => true,
    ]);

    $department = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $departmentType->id,
        'parent_id' => $branch->id,
        'code' => 'DEPT-LU',
        'name' => 'Lookup Department',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'first_name' => 'Lookup',
        'last_name' => 'Candidate',
        'id_number' => 'LU-10001',
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
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
        'organizational_unit_id' => $department->id,
        'organization_id' => null,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $branchEmployee = Employee::factory()->create([
        'first_name' => 'Branch',
        'last_name' => 'Anchor',
        'id_number' => 'LU-BR-01',
    ]);

    $branchEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $branchEmployee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $branchEmployee->id,
        'employee_employment_id' => $branchEmployment->id,
        'organization_id' => $org->id,
        'root_unit_id' => $branch->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $branchEmployee->id,
        'employee_employment_id' => $branchEmployment->id,
        'organizational_unit_id' => $branch->id,
        'organization_id' => null,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    return [
        'org' => $org,
        'branch' => $branch,
        'department' => $department,
        'employee' => $employee,
        'branchEmployee' => $branchEmployee,
    ];
}

test('team hr lookup routes are forbidden for plain employees', function (): void {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->getJson(route('team-hr.units.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => 1,
            'unit_id' => 1,
            'q' => 'ab',
        ]))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('team-hr.organization-holidays.index', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('team-hr.leave-period.expand', [
            'chart_branch_id' => 1,
            'unit_id' => 1,
            'employee_id' => 1,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-07',
        ]))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('team-hr.leave-usage-summary.index', [
            'chart_branch_id' => 1,
            'unit_id' => 1,
            'employee_id' => 1,
            'leave_type_code' => 'VL',
        ]))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('team-hr.employees.decision-makers.search', [
            'chart_branch_id' => 1,
            'q' => 'ab',
        ]))
        ->assertForbidden();
});

test('team hr units index includes workspace branch root and lists descendants', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $response = $this->actingAs($user)
        ->getJson(route('team-hr.units.index'))
        ->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'code', 'name', 'parent_id'],
        ],
        'meta' => ['workspace_branch_id'],
    ]);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($ctx['department']->id)
        ->toContain($ctx['branch']->id);
});

test('team hr employee search accepts workspace branch unit for assignments on that unit', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $response = $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['branch']->id,
            'q' => 'Bran',
            'limit' => 10,
        ]))
        ->assertOk();

    $response->assertJsonPath('data.0.id', $ctx['branchEmployee']->id);
});

test('team hr employee search includes attendance id and work schedule template payload', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $ctx['org']->id,
        'name' => 'Fixture Shift',
        'clock_pattern' => WorkScheduleClockPattern::SinglePair,
        'segments' => null,
        'time_in' => '08:00',
        'time_out' => '17:00',
        'is_overnight' => false,
    ]);

    $ctx['employee']->update([
        'attendance_id' => 'BIO-999',
        'work_schedule_template_id' => $template->id,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $response = $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'q' => 'Look',
            'limit' => 10,
        ]))
        ->assertOk();

    $response->assertJsonPath('data.0.attendance_id', 'BIO-999')
        ->assertJsonPath('data.0.work_schedule_template_id', $template->id)
        ->assertJsonPath('data.0.work_schedule_template.name', 'Fixture Shift')
        ->assertJsonPath('data.0.work_schedule_template.clock_pattern', 'single_pair');
});

test('team hr employee search returns matches for unit assignment', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $response = $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'q' => 'Look',
            'limit' => 10,
        ]))
        ->assertOk();

    $response->assertJsonPath('data.0.id', $ctx['employee']->id);
    $response->assertJsonPath('data.0.employee_number', 'LU-10001');
});

test('team hr employee search lists employees when query is omitted', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'limit' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $ctx['employee']->id);
});

test('team hr employee search filters on a single character query', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'q' => 'L',
            'limit' => 10,
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $ctx['employee']->id);
});

test('team hr employee search rejects mismatched workspace branch id', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => $ctx['branch']->id + 99999,
            'unit_id' => $ctx['department']->id,
            'q' => 'Look',
        ]))
        ->assertForbidden();
});

test('team hr decision maker search validates minimum query length', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $this->actingAs($user)
        ->getJson(route('team-hr.employees.decision-makers.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'q' => 'L',
        ]))
        ->assertUnprocessable();
});

test('team hr decision maker search returns branch-wide matches', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $response = $this->actingAs($user)
        ->getJson(route('team-hr.employees.decision-makers.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'q' => 'Look',
            'limit' => 10,
        ]))
        ->assertOk();

    $response->assertJsonPath('data.0.id', $ctx['employee']->id);
});

test('team hr employee search rejects unit outside selectable subtree', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ]);

    $this->actingAs($user)
        ->getJson(route('team-hr.employees.search', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => 999_999_999,
            'q' => 'Look',
        ]))
        ->assertNotFound();
});

test('team hr units index excludes head office apex when it appears under workspace root', function (): void {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-TEAM-HR-HO',
        'name' => 'Team HR HO Org',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-TEAM-HR-HO']);

    $hoType = UnitType::query()->create([
        'name' => 'Head Office',
        'color' => '#111111',
        'can_be_root' => true,
        'description' => 'Test HO',
        'is_active' => true,
    ]);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $departmentType = UnitType::query()->where('name', 'Department')->firstOrFail();

    $headOffice = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $hoType->id,
        'parent_id' => null,
        'code' => 'HO-T',
        'name' => 'Test Head Office',
        'is_active' => true,
    ]);

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => $headOffice->id,
        'code' => 'BR-HO',
        'name' => 'Branch Under HO',
        'is_active' => true,
    ]);

    $department = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $departmentType->id,
        'parent_id' => $branch->id,
        'code' => 'DEPT-HO',
        'name' => 'Dept Under Branch',
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $headOffice->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $response = $this->actingAs($user)
        ->getJson(route('team-hr.units.index'))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($branch->id)
        ->toContain($department->id)
        ->not->toContain($headOffice->id);
});

test('team hr organization holidays index returns overlapping holiday rules json', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $type = HolidayType::query()->create([
        'organization_id' => $ctx['org']->id,
        'slug' => 'lu-regular-hol-type',
        'name' => 'Regular holiday type',
        'kind' => 'builtin',
        'color_key' => 'sky',
        'pay_policy' => 'No Premium',
    ]);

    OrganizationHoliday::query()->create([
        'organization_id' => $ctx['org']->id,
        'holiday_type_id' => $type->id,
        'name' => 'Lookup holiday window',
        'start_date' => '2026-08-05',
        'end_date' => '2026-08-06',
        'notes' => null,
        'recurrence' => null,
    ]);

    OrganizationHoliday::query()->create([
        'organization_id' => $ctx['org']->id,
        'holiday_type_id' => $type->id,
        'name' => 'Outside filtered range',
        'start_date' => '2026-12-20',
        'end_date' => '2026-12-20',
        'notes' => null,
        'recurrence' => null,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->getJson(route('team-hr.organization-holidays.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Lookup holiday window')
        ->assertJsonPath('data.0.start_date', '2026-08-05');

    expect(
        OrganizationHoliday::query()->where('name', 'Outside filtered range')->exists(),
    )->toBeTrue();
});

test('team hr organization holidays index rejects oversized date ranges', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->getJson(route('team-hr.organization-holidays.index', [
            'date_from' => '2020-01-01',
            'date_to' => '2024-06-01',
        ]))
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['date_to']]);
});

test('team hr leave period expand returns 422 when employee has no work schedule', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $ctx['employee']->update(['work_schedule_template_id' => null]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->getJson(route('team-hr.leave-period.expand', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'date_from' => '2026-01-12',
            'date_to' => '2026-01-14',
        ]))
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['employee_id']]);
});

test('team hr leave period expand succeeds without attendance id when work schedule is assigned', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $ctx['org']->id,
    ]);

    $ctx['employee']->update([
        'attendance_id' => null,
        'work_schedule_template_id' => $template->id,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->getJson(route('team-hr.leave-period.expand', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'date_from' => '2026-01-12',
            'date_to' => '2026-01-14',
        ]))
        ->assertOk();
});

test('team hr leave period expand uses work schedule weekdays and skips organization holidays', function (): void {
    (new RoleSeeder)->run();
    $ctx = seedTeamHrLookupScenario();

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $ctx['org']->id,
    ]);

    $ctx['employee']->update([
        'work_schedule_template_id' => $template->id,
    ]);

    $type = HolidayType::query()->create([
        'organization_id' => $ctx['org']->id,
        'slug' => 'leave-expand-regular',
        'name' => 'Regular holiday type',
        'kind' => 'builtin',
        'color_key' => 'sky',
        'pay_policy' => 'No Premium',
    ]);

    OrganizationHoliday::query()->create([
        'organization_id' => $ctx['org']->id,
        'holiday_type_id' => $type->id,
        'name' => 'Mid-week holiday',
        'start_date' => '2026-01-13',
        'end_date' => '2026-01-13',
        'notes' => null,
        'recurrence' => null,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $response = $this->actingAs($user)
        ->getJson(route('team-hr.leave-period.expand', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'date_from' => '2026-01-12',
            'date_to' => '2026-01-14',
        ]))
        ->assertOk();

    $dates = collect($response->json('data.counted'))->pluck('date')->all();
    expect($dates)->toMatchArray(['2026-01-12', '2026-01-14']);

    $response->assertJsonPath('data.skipped.0.date', '2026-01-13')
        ->assertJsonPath('data.skipped.0.reason', 'Holiday');
});

test('team hr leave usage summary aggregates counted leave days and detects overlaps', function (): void {
    (new RoleSeeder)->run();
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

    $ctx = seedTeamHrLookupScenario();

    $policy = LeavePolicy::factory()->create([
        'organization_id' => $ctx['org']->id,
        'code' => 'USLG',
        'name' => 'Usage summary fixture',
        'annual_entitlement' => 18,
    ]);

    $leave = EmployeeLeave::factory()->create([
        'organization_id' => $ctx['org']->id,
        'employee_id' => $ctx['employee']->id,
        'organizational_unit_id' => $ctx['department']->id,
        'leave_policy_id' => $policy->id,
        'approver_employee_id' => $ctx['branchEmployee']->id,
        'status' => EmployeeHrRecordStatus::Approved,
        'start_date' => '2026-06-02',
        'end_date' => '2026-06-03',
        'submitted_at' => '2026-06-01',
        'decided_at' => '2026-06-01',
    ]);

    app(EmployeeLeaveDaysSyncService::class)->sync($leave, [
        ['date' => '2026-06-02', 'is_half_day' => false],
        ['date' => '2026-06-03', 'is_half_day' => true],
    ]);

    expect(EmployeeLeaveDay::query()->where('employee_leave_id', $leave->id)->count('*'))->toBe(2);

    expect(
        (int) DB::table('employee_leave_days as eld')
            ->join('employee_leaves as el', 'eld.employee_leave_id', '=', 'el.id')
            ->join('leave_policies as lp', 'el.leave_policy_id', '=', 'lp.id')
            ->where('eld.organization_id', $ctx['org']->id)
            ->where('eld.employee_id', $ctx['employee']->id)
            ->whereNull('el.deleted_at')
            ->where('el.status', EmployeeHrRecordStatus::Approved->value)
            ->whereYear('eld.leave_date', 2026)
            ->count(),
    )->toBe(2);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->getJson(route('team-hr.leave-usage-summary.index', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'leave_type_code' => 'USLG',
        ]))
        ->assertOk()
        // One full day + one half day = 1.5 leave units (see leaveUnitsTotal in leaveDaysDraftUtils.ts).
        ->assertJsonPath('data.same_leave_type_code.approved_leave_units_week', 0)
        ->assertJsonPath('data.same_leave_type_code.approved_leave_units_month', 1.5)
        ->assertJsonPath('data.same_leave_type_code.approved_leave_units_year', 1.5)
        ->assertJsonPath('data.reference.year', 2026);

    $overlap = $this->actingAs($user)
        ->getJson(route('team-hr.leave-usage-summary.index', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'leave_type_code' => 'USLG',
            'draft_dates' => ['2026-06-03'],
        ]))
        ->assertOk();

    expect(collect($overlap->json('data.overlaps'))->pluck('date')->unique()->sort()->values()->all())->toContain('2026-06-03');

    $exclude = $this->actingAs($user)
        ->getJson(route('team-hr.leave-usage-summary.index', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'leave_type_code' => 'USLG',
            'exclude_employee_leave_id' => $leave->id,
            'draft_dates' => ['2026-06-03'],
        ]))
        ->assertOk();

    expect($exclude->json('data.overlaps'))->toEqual([]);
});

test('team hr overtime usage summary aggregates approved hours by week month year', function (): void {
    (new RoleSeeder)->run();
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

    $ctx = seedTeamHrLookupScenario();

    $policy = OvertimePolicy::factory()->create([
        'organization_id' => $ctx['org']->id,
        'code' => 'OT-USE',
        'name' => 'Usage summary overtime',
    ]);

    $weekOvertime = EmployeeOvertime::factory()->create([
        'organization_id' => $ctx['org']->id,
        'employee_id' => $ctx['employee']->id,
        'organizational_unit_id' => $ctx['department']->id,
        'overtime_policy_id' => $policy->id,
        'approver_employee_id' => $ctx['branchEmployee']->id,
        'status' => EmployeeHrRecordStatus::Approved,
        'ot_date' => '2026-06-15',
        'hours' => 1.00,
        'submitted_at' => '2026-06-15',
        'decided_at' => '2026-06-15',
    ]);

    EmployeeOvertime::factory()->create([
        'organization_id' => $ctx['org']->id,
        'employee_id' => $ctx['employee']->id,
        'organizational_unit_id' => $ctx['department']->id,
        'overtime_policy_id' => $policy->id,
        'approver_employee_id' => $ctx['branchEmployee']->id,
        'status' => EmployeeHrRecordStatus::Approved,
        'ot_date' => '2026-06-10',
        'hours' => 4.00,
        'submitted_at' => '2026-06-10',
        'decided_at' => '2026-06-10',
    ]);

    EmployeeOvertime::factory()->create([
        'organization_id' => $ctx['org']->id,
        'employee_id' => $ctx['employee']->id,
        'organizational_unit_id' => $ctx['department']->id,
        'overtime_policy_id' => $policy->id,
        'approver_employee_id' => $ctx['branchEmployee']->id,
        'status' => EmployeeHrRecordStatus::Approved,
        'ot_date' => '2026-02-10',
        'hours' => 11.00,
        'submitted_at' => '2026-02-10',
        'decided_at' => '2026-02-10',
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $ctx['branch']->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->getJson(route('team-hr.overtime-usage-summary.index', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'policy_code' => 'OT-USE',
        ]))
        ->assertOk()
        ->assertJsonPath('data.same_policy_code.approved_hours_week', 1)
        ->assertJsonPath('data.same_policy_code.approved_hours_month', 5)
        ->assertJsonPath('data.same_policy_code.approved_hours_year', 16);

    $this->actingAs($user)
        ->getJson(route('team-hr.overtime-usage-summary.index', [
            'chart_branch_id' => $ctx['branch']->id,
            'unit_id' => $ctx['department']->id,
            'employee_id' => $ctx['employee']->id,
            'policy_code' => 'OT-USE',
            'exclude_employee_overtime_id' => $weekOvertime->id,
        ]))
        ->assertOk()
        ->assertJsonPath('data.same_policy_code.approved_hours_week', 0)
        ->assertJsonPath('data.same_policy_code.approved_hours_month', 4)
        ->assertJsonPath('data.same_policy_code.approved_hours_year', 15);
});
