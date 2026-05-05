<?php

use App\Enums\AttendanceRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\DevelopmentEmployeeAttendanceDaysSeeder;
use Database\Seeders\DevelopmentEmployeeAttendanceProfileSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkScheduleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('development employee attendance days seeder creates days only for employees with attendance id and work schedule template', function (): void {
    $this->seed([
        OrganizationalStructureSeeder::class,
        RoleSeeder::class,
        DemoCooperativeSeeder::class,
        DevelopmentUserSeeder::class,
        WorkScheduleTemplatesSeeder::class,
        DevelopmentEmployeeAttendanceProfileSeeder::class,
        DevelopmentEmployeeAttendanceDaysSeeder::class,
    ]);

    $allSeedEmployees = Employee::query()
        ->where('id_number', 'like', 'EMP-SEED-%')
        ->get();

    expect($allSeedEmployees)->not->toBeEmpty();

    $eligibleIds = Employee::query()
        ->where('id_number', 'like', 'EMP-SEED-%')
        ->whereNotNull('attendance_id')
        ->whereNotNull('work_schedule_template_id')
        ->whereRaw('TRIM(attendance_id) <> ?', [''])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $workDate = '2026-05-15';

    $dayCount = EmployeeAttendanceDay::query()
        ->whereIn('employee_id', $eligibleIds)
        ->whereDate('work_date', $workDate)
        ->count();

    expect($dayCount)->toBe(count($eligibleIds))
        ->and($eligibleIds)->toHaveCount(11);

    foreach (['EMP-SEED-009', 'EMP-SEED-010', 'EMP-SEED-012'] as $idNumber) {
        $excluded = Employee::query()->where('id_number', $idNumber)->value('id');

        expect(EmployeeAttendanceDay::query()
            ->where('employee_id', $excluded)
            ->whereDate('work_date', $workDate)
            ->exists())->toBeFalse();
    }

    $splitEmployee = Employee::query()->where('id_number', 'EMP-SEED-003')->firstOrFail();
    $splitDay = EmployeeAttendanceDay::query()
        ->where('employee_id', $splitEmployee->id)
        ->whereDate('work_date', $workDate)
        ->with('segments')
        ->firstOrFail();

    expect($splitDay->clock_pattern)->toBe(WorkScheduleClockPattern::SplitSessions)
        ->and($splitDay->segments)->toHaveCount(3)
        ->and($splitDay->segments[0]->scheduled_in)->toBe('08:00')
        ->and($splitDay->segments[1]->scheduled_in)->toBe('13:00')
        ->and($splitDay->segments[2]->scheduled_in)->toBe('17:00')
        ->and($splitDay->status)->toBe(AttendanceRecordStatus::Complete)
        ->and((float) $splitDay->net_hours)->toBe(10.0);

    $splitEmployee008 = Employee::query()->where('id_number', 'EMP-SEED-008')->firstOrFail();
    $splitDay008 = EmployeeAttendanceDay::query()
        ->where('employee_id', $splitEmployee008->id)
        ->whereDate('work_date', $workDate)
        ->with('segments')
        ->firstOrFail();

    expect($splitDay008->clock_pattern)->toBe(WorkScheduleClockPattern::SplitSessions)
        ->and($splitDay008->segments)->toHaveCount(3)
        ->and($splitDay008->segments[2]->scheduled_in)->toBe('17:00')
        ->and($splitDay008->status)->toBe(AttendanceRecordStatus::Complete)
        ->and((float) $splitDay008->net_hours)->toBe(10.0)
        ->and($splitDay008->ingest_key)->toStartWith('ATD-20260515-');

    $lateDayIds = Employee::query()
        ->whereIn('id_number', ['EMP-SEED-001', 'EMP-SEED-007', 'EMP-SEED-011'])
        ->pluck('id')
        ->all();

    foreach ($lateDayIds as $lateEmpId) {
        $lateDay = EmployeeAttendanceDay::query()
            ->where('employee_id', $lateEmpId)
            ->whereDate('work_date', $workDate)
            ->firstOrFail();

        expect($lateDay->status)->toBe(AttendanceRecordStatus::Complete)
            ->and($lateDay->punctuality)->toBe('late');
    }

    $onTimeEmployees = Employee::query()
        ->whereIn('id_number', ['EMP-SEED-002', 'EMP-SEED-003', 'EMP-SEED-004'])
        ->pluck('id');

    foreach ($onTimeEmployees as $onEmpId) {
        $day = EmployeeAttendanceDay::query()
            ->where('employee_id', $onEmpId)
            ->whereDate('work_date', $workDate)
            ->firstOrFail();

        expect($day->status)->toBe(AttendanceRecordStatus::Complete)
            ->and($day->punctuality)->toBe('on_time');
    }

    $weekendTemplateDay = EmployeeAttendanceDay::query()
        ->where('employee_id', Employee::query()->where('id_number', 'EMP-SEED-005')->value('id'))
        ->whereDate('work_date', $workDate)
        ->with('segments')
        ->firstOrFail();

    expect($weekendTemplateDay->status)->toBe(AttendanceRecordStatus::Complete)
        ->and((float) $weekendTemplateDay->net_hours)->toBe(10.0);

    $otExtendedDay = EmployeeAttendanceDay::query()
        ->where('employee_id', Employee::query()->where('id_number', 'EMP-SEED-014')->value('id'))
        ->whereDate('work_date', $workDate)
        ->with('segments')
        ->firstOrFail();

    expect($otExtendedDay->clock_pattern)->toBe(WorkScheduleClockPattern::SplitSessions)
        ->and($otExtendedDay->segments)->toHaveCount(3)
        ->and($otExtendedDay->segments[2]->scheduled_in)->toBe('17:00')
        ->and($otExtendedDay->status)->toBe(AttendanceRecordStatus::Complete)
        ->and((float) $otExtendedDay->net_hours)->toBe(10.0)
        ->and((string) $otExtendedDay->segments[2]->actual_out)->toBeGreaterThan((string) $otExtendedDay->segments[2]->scheduled_out);

    expect(EmployeeAttendanceDay::query()
        ->whereIn('employee_id', $eligibleIds)
        ->whereDate('work_date', $workDate)
        ->where('status', AttendanceRecordStatus::Complete)
        ->count())->toBe(count($eligibleIds));
});
