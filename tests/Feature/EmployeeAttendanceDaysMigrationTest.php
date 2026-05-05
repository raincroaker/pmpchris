<?php

use App\Enums\AttendanceEntrySource;
use App\Enums\AttendanceRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an attendance day with work schedule template snapshot and segments', function (): void {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create();
    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $organization->id,
        'clock_pattern' => WorkScheduleClockPattern::SplitSessions,
        'is_overnight' => false,
    ]);

    $day = EmployeeAttendanceDay::query()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'organizational_unit_id' => null,
        'work_date' => '2026-05-10',
        'work_schedule_template_id' => $template->id,
        'clock_pattern' => WorkScheduleClockPattern::SplitSessions,
        'is_overnight_schedule' => false,
        'ingest_key' => 'AC-TEST-001',
        'original_entry_source' => AttendanceEntrySource::Device,
        'last_modified_source' => AttendanceEntrySource::Device,
        'status' => AttendanceRecordStatus::Complete,
        'punctuality' => 'on_time',
        'net_hours' => 8.5,
        'variance_label' => null,
    ]);

    EmployeeAttendanceSegment::query()->create([
        'employee_attendance_day_id' => $day->id,
        'segment_index' => 0,
        'label' => 'Session 1',
        'scheduled_in' => '08:30',
        'scheduled_out' => '12:00',
        'actual_in' => '08:28',
        'actual_out' => '12:02',
    ]);

    $day->load('workScheduleTemplate', 'segments');

    expect($day->workScheduleTemplate->is($template))->toBeTrue()
        ->and($day->segments)->toHaveCount(1)
        ->and($day->original_entry_source)->toBe(AttendanceEntrySource::Device);
});

it('enforces one attendance day per employee per work date', function (): void {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create();
    $template = WorkScheduleTemplate::factory()->create(['organization_id' => $organization->id]);

    EmployeeAttendanceDay::query()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'organizational_unit_id' => null,
        'work_date' => '2026-05-11',
        'work_schedule_template_id' => $template->id,
        'clock_pattern' => WorkScheduleClockPattern::SinglePair,
        'is_overnight_schedule' => false,
        'ingest_key' => null,
        'original_entry_source' => AttendanceEntrySource::Manual,
        'last_modified_source' => AttendanceEntrySource::Manual,
        'status' => AttendanceRecordStatus::Complete,
        'punctuality' => null,
        'net_hours' => null,
        'variance_label' => null,
    ]);

    expect(fn () => EmployeeAttendanceDay::query()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'organizational_unit_id' => null,
        'work_date' => '2026-05-11',
        'work_schedule_template_id' => $template->id,
        'clock_pattern' => WorkScheduleClockPattern::SinglePair,
        'is_overnight_schedule' => false,
        'ingest_key' => null,
        'original_entry_source' => AttendanceEntrySource::Import,
        'last_modified_source' => AttendanceEntrySource::Import,
        'status' => AttendanceRecordStatus::Incomplete,
        'punctuality' => null,
        'net_hours' => null,
        'variance_label' => null,
    ]))->toThrow(QueryException::class);
});

it('records deleted_by_user_id on soft delete when authenticated', function (): void {
    $user = User::factory()->create();
    $day = EmployeeAttendanceDay::factory()->create();

    $this->actingAs($user);
    $day->delete();

    $trashed = EmployeeAttendanceDay::onlyTrashed()->findOrFail($day->id);

    expect($trashed->deleted_at)->not->toBeNull()
        ->and($trashed->deleted_by_user_id)->toBe($user->id);
});
