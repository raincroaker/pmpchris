<?php

use App\Enums\AttendanceEntrySource;
use App\Enums\AttendanceRecordStatus;
use App\Exports\DtrMockExport;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveDay;
use App\Models\EmployeeOvertime;
use App\Models\HolidayType;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use App\Models\OvertimePolicy;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\UnitType;
use App\Models\WorkScheduleTemplate;
use App\Services\TeamAttendanceDtrExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('team mode builds per-employee exports with no-schedule fallback', function (): void {
    $organization = Organization::factory()->create();
    $unitType = UnitType::factory()->create(['can_be_root' => true]);
    $rootUnit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'name' => 'Root Unit',
    ]);
    $teamUnit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => $rootUnit->id,
        'name' => 'Team Unit',
    ]);

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $employeeWithSchedule = Employee::factory()->create([
        'last_name' => 'Alba',
        'first_name' => 'Ana',
        'middle_name' => 'Lopez',
        'work_schedule_template_id' => $template->id,
        'attendance_id' => 'ATT-1001',
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $employeeWithSchedule->id,
        'organizational_unit_id' => $teamUnit->id,
        'is_primary' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'HR Specialist',
    ]);
    \App\Models\EmployeePosition::factory()->create([
        'employee_id' => $employeeWithSchedule->id,
        'position_id' => $position->id,
        'is_primary' => true,
    ]);

    $day = EmployeeAttendanceDay::factory()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employeeWithSchedule->id,
        'organizational_unit_id' => $teamUnit->id,
        'work_date' => '2026-05-05',
        'work_schedule_template_id' => $template->id,
        'status' => AttendanceRecordStatus::Complete,
        'original_entry_source' => AttendanceEntrySource::Manual,
        'last_modified_source' => AttendanceEntrySource::Manual,
        'net_hours' => 8.0,
    ]);
    EmployeeAttendanceSegment::factory()->create([
        'employee_attendance_day_id' => $day->id,
        'segment_index' => 0,
        'label' => 'Shift',
        'scheduled_in' => '09:00',
        'scheduled_out' => '17:00',
        'actual_in' => '09:00',
        'actual_out' => '17:00',
    ]);

    $leavePolicy = LeavePolicy::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'VL',
        'name' => 'Vacation Leave',
    ]);
    $leave = EmployeeLeave::factory()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employeeWithSchedule->id,
        'organizational_unit_id' => $teamUnit->id,
        'leave_policy_id' => $leavePolicy->id,
        'start_date' => '2026-05-05',
        'end_date' => '2026-05-05',
        'status' => \App\Enums\EmployeeHrRecordStatus::Approved,
    ]);
    EmployeeLeaveDay::query()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employeeWithSchedule->id,
        'employee_leave_id' => $leave->id,
        'leave_date' => '2026-05-05',
        'is_half_day' => false,
    ]);

    $otPolicy = OvertimePolicy::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'REGOT',
        'name' => 'Regular Overtime',
    ]);
    EmployeeOvertime::factory()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employeeWithSchedule->id,
        'organizational_unit_id' => $teamUnit->id,
        'overtime_policy_id' => $otPolicy->id,
        'ot_date' => '2026-05-05',
        'hours' => 2.0,
        'status' => \App\Enums\EmployeeHrRecordStatus::Approved,
    ]);

    $holidayType = HolidayType::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Regular Holiday',
        'slug' => 'regular-holiday',
        'kind' => 'regular',
        'color_key' => 'lime',
        'pay_policy' => 'double_pay',
    ]);
    OrganizationHoliday::query()->create([
        'organization_id' => $organization->id,
        'holiday_type_id' => $holidayType->id,
        'name' => 'Labor Day',
        'start_date' => '2026-05-05',
        'end_date' => '2026-05-05',
    ]);

    $employeeNoSchedule = Employee::factory()->create([
        'last_name' => 'Bautista',
        'first_name' => 'Ben',
        'work_schedule_template_id' => null,
        'attendance_id' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $employeeNoSchedule->id,
        'organizational_unit_id' => $teamUnit->id,
        'is_primary' => true,
    ]);

    /** @var TeamAttendanceDtrExportService $service */
    $service = app(TeamAttendanceDtrExportService::class);
    $exports = $service->buildExportsForScope(
        (int) $organization->id,
        (int) $rootUnit->id,
        'team',
        null,
        (int) $teamUnit->id,
        '2026-05-05',
        '2026-05-05',
    );

    expect($exports)->toHaveCount(2)
        ->and($exports[0])->toBeInstanceOf(DtrMockExport::class)
        ->and($exports[1])->toBeInstanceOf(DtrMockExport::class);

    /** @var DtrMockExport $first */
    $first = $exports[0];
    /** @var DtrMockExport $second */
    $second = $exports[1];

    expect($first->employeeName)->toContain('Alba')
        ->and($first->employeeName)->toContain('Ana L.')
        ->and($first->branchOrDept)->toContain('Root Unit - Team Unit')
        ->and($first->attendanceId)->toBe('ATT-1001')
        ->and($first->position)->toBe('HR Specialist')
        ->and($first->rows->first()['amIn'])->toBe('09:00')
        ->and($first->rows->first()['pmOut'])->toBe('17:00')
        ->and($first->rows->first()['amOut'])->toBe('')
        ->and($first->rows->first()['pmIn'])->toBe('')
        ->and($first->otRequests->first()['type'])->toContain('[REGOT]')
        ->and($first->leaveRequests->first()['type'])->toContain('[VL]')
        ->and($first->holidaysInMonth->first()['name'])->toContain('(Regular Holiday)');

    expect($second->employeeName)->toContain('Bautista')
        ->and($second->workScheduleReference)->not->toBeNull()
        ->and($second->workScheduleReference['template_name'])->toBe('—')
        ->and($second->grossTimeTotal)->toBe('—')
        ->and($second->netTimeTotal)->toBe('—')
        ->and($second->rows->first()['spanMiddle'])->toBeNull();
});

test('single pair dtr row uses first actual in and last actual out across captured segments', function (): void {
    $organization = Organization::factory()->create();
    $unitType = UnitType::factory()->create(['can_be_root' => true]);
    $rootUnit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'name' => 'Root Unit',
    ]);
    $teamUnit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => $rootUnit->id,
        'name' => 'Team Unit',
    ]);

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $employee = Employee::factory()->create([
        'last_name' => 'Cruz',
        'first_name' => 'Cara',
        'work_schedule_template_id' => $template->id,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $teamUnit->id,
        'is_primary' => true,
    ]);

    $day = EmployeeAttendanceDay::factory()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'organizational_unit_id' => $teamUnit->id,
        'work_date' => '2026-05-06',
        'work_schedule_template_id' => $template->id,
        'status' => AttendanceRecordStatus::Complete,
        'original_entry_source' => AttendanceEntrySource::Manual,
        'last_modified_source' => AttendanceEntrySource::Manual,
        'net_hours' => 8.0,
    ]);
    EmployeeAttendanceSegment::factory()->create([
        'employee_attendance_day_id' => $day->id,
        'segment_index' => 0,
        'label' => 'Shift',
        'scheduled_in' => '09:00',
        'scheduled_out' => '17:00',
        'actual_in' => '08:55',
        'actual_out' => '12:00',
    ]);
    EmployeeAttendanceSegment::factory()->create([
        'employee_attendance_day_id' => $day->id,
        'segment_index' => 1,
        'label' => 'Extra',
        'scheduled_in' => '13:00',
        'scheduled_out' => '17:00',
        'actual_in' => '13:05',
        'actual_out' => '18:10',
    ]);

    /** @var TeamAttendanceDtrExportService $service */
    $service = app(TeamAttendanceDtrExportService::class);
    $exports = $service->buildExportsForScope(
        (int) $organization->id,
        (int) $rootUnit->id,
        'individual',
        (int) $employee->id,
        null,
        '2026-05-06',
        '2026-05-06',
    );

    expect($exports)->toHaveCount(1);
    /** @var DtrMockExport $export */
    $export = $exports[0];
    $row = $export->rows->first();

    expect($row['amIn'])->toBe('08:55')
        ->and($row['pmOut'])->toBe('18:10')
        ->and($row['amOut'])->toBe('')
        ->and($row['pmIn'])->toBe('')
        ->and($row['otIn'])->toBe('')
        ->and($row['otOut'])->toBe('');
});

