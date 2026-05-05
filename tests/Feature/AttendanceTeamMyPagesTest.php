<?php

use App\Models\User;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use App\Models\WorkScheduleTemplate;
use Database\Seeders\OrganizationalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

describe('team and my attendance pages', function (): void {
    test('guests cannot visit team attendance', function (): void {
        $this->get(route('attendance.team'))->assertRedirect(route('login'));
    });

    test('guests cannot visit my attendance', function (): void {
        $this->get(route('attendance.my'))->assertRedirect(route('login'));
    });

    test('team attendance requires hr team access', function (): void {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('attendance.team'))
            ->assertForbidden();
    });

    test('authenticated users can visit my attendance', function (): void {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('attendance.my'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/My')
                ->has('myAttendanceDays.data')
                ->has('myAttendanceFilters')
                ->where('hasEmployeeRecord', false));
    });

    test('my attendance returns server rows for signed in employee only', function (): void {
        (new OrganizationalStructureSeeder)->run();

        $org = Organization::factory()->create([
            'code' => 'MY-ATT-TEST',
            'name' => 'My attendance test org',
            'is_active' => true,
        ]);
        config(['hris.default_organization_code' => 'MY-ATT-TEST']);

        $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
        $branch = OrganizationalUnit::factory()->create([
            'organization_id' => $org->id,
            'unit_type_id' => $branchType->id,
            'parent_id' => null,
            'code' => 'MY-AT-BR',
            'name' => 'My attendance branch',
            'is_active' => true,
        ]);

        $template = WorkScheduleTemplate::factory()->create([
            'organization_id' => $org->id,
            'name' => 'My attendance template',
            'time_in' => '08:00',
            'time_out' => '17:00',
        ]);

        $employee = Employee::factory()->create([
            'attendance_id' => 'MY-ATT-EMP-1',
            'work_schedule_template_id' => $template->id,
        ]);
        $otherEmployee = Employee::factory()->create([
            'attendance_id' => 'MY-ATT-EMP-2',
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

        $otherEmployment = EmployeeEmployment::factory()->create([
            'employee_id' => $otherEmployee->id,
            'is_current' => true,
        ]);
        EmployeeAffiliation::query()->create([
            'employee_id' => $otherEmployee->id,
            'employee_employment_id' => $otherEmployment->id,
            'organization_id' => $org->id,
            'root_unit_id' => $branch->id,
            'is_primary' => true,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
        ]);

        $myDay = EmployeeAttendanceDay::factory()->create([
            'organization_id' => $org->id,
            'employee_id' => $employee->id,
            'organizational_unit_id' => $branch->id,
            'work_schedule_template_id' => $template->id,
            'work_date' => '2026-05-12',
            'ingest_key' => 'MY-ROW-1',
        ]);
        EmployeeAttendanceSegment::factory()->create([
            'employee_attendance_day_id' => $myDay->id,
            'segment_index' => 0,
            'label' => 'Shift',
            'scheduled_in' => '08:00',
            'scheduled_out' => '17:00',
            'actual_in' => '08:02',
            'actual_out' => '17:01',
        ]);

        $otherDay = EmployeeAttendanceDay::factory()->create([
            'organization_id' => $org->id,
            'employee_id' => $otherEmployee->id,
            'organizational_unit_id' => $branch->id,
            'work_schedule_template_id' => $template->id,
            'work_date' => '2026-05-12',
            'ingest_key' => 'OTHER-ROW-1',
        ]);
        EmployeeAttendanceSegment::factory()->create([
            'employee_attendance_day_id' => $otherDay->id,
            'segment_index' => 0,
            'label' => 'Shift',
            'scheduled_in' => '08:00',
            'scheduled_out' => '17:00',
            'actual_in' => '08:05',
            'actual_out' => '17:00',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($user)
            ->get(route('attendance.my', [
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'recording_style' => 'simple',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/My')
                ->where('hasEmployeeRecord', true)
                ->where('myAttendanceDays.total', 1)
                ->has('myAttendanceDays.data', 1)
                ->where('myAttendanceDays.data.0.ingest_key', 'MY-ROW-1')
                ->where('myAttendanceFilters.recording_style', 'simple'));
    });

test('my attendance supports work date sort direction toggle', function (): void {
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'MY-ATT-SORT',
        'name' => 'My attendance sort org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'MY-ATT-SORT']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'MY-SORT-BR',
        'name' => 'My sort branch',
        'is_active' => true,
    ]);

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $org->id,
        'name' => 'My sort template',
        'time_in' => '08:00',
        'time_out' => '17:00',
    ]);

    $employee = Employee::factory()->create([
        'attendance_id' => 'MY-SORT-EMP',
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

    $olderDay = EmployeeAttendanceDay::factory()->create([
        'organization_id' => $org->id,
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_schedule_template_id' => $template->id,
        'work_date' => '2026-05-10',
        'ingest_key' => 'MY-SORT-OLDER',
    ]);
    EmployeeAttendanceSegment::factory()->create([
        'employee_attendance_day_id' => $olderDay->id,
        'segment_index' => 0,
        'label' => 'Shift',
        'scheduled_in' => '08:00',
        'scheduled_out' => '17:00',
        'actual_in' => '08:00',
        'actual_out' => '17:00',
    ]);

    $newerDay = EmployeeAttendanceDay::factory()->create([
        'organization_id' => $org->id,
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'work_schedule_template_id' => $template->id,
        'work_date' => '2026-05-20',
        'ingest_key' => 'MY-SORT-NEWER',
    ]);
    EmployeeAttendanceSegment::factory()->create([
        'employee_attendance_day_id' => $newerDay->id,
        'segment_index' => 0,
        'label' => 'Shift',
        'scheduled_in' => '08:00',
        'scheduled_out' => '17:00',
        'actual_in' => '08:01',
        'actual_out' => '17:01',
    ]);

    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($user)
        ->get(route('attendance.my', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'recording_style' => 'simple',
            'sort' => 'work_date',
            'direction' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/My')
            ->where('myAttendanceFilters.sort', 'work_date')
            ->where('myAttendanceFilters.direction', 'asc')
            ->where('myAttendanceDays.data.0.ingest_key', 'MY-SORT-OLDER')
            ->where('myAttendanceDays.data.1.ingest_key', 'MY-SORT-NEWER'));

    $this->actingAs($user)
        ->get(route('attendance.my', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'recording_style' => 'simple',
            'sort' => 'work_date',
            'direction' => 'desc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/My')
            ->where('myAttendanceFilters.sort', 'work_date')
            ->where('myAttendanceFilters.direction', 'desc')
            ->where('myAttendanceDays.data.0.ingest_key', 'MY-SORT-NEWER')
            ->where('myAttendanceDays.data.1.ingest_key', 'MY-SORT-OLDER'));
});
});
