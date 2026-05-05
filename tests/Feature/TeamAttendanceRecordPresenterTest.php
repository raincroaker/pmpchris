<?php

use App\Enums\AttendanceEntrySource;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeAttendanceDay;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use App\Support\TeamAttendanceRecordPresenter;
use App\Support\TeamHrEmployeeDirectoryExtras;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('team attendance presenter exposes original and last modified sources plus audit timestamps', function (): void {
    $editor = User::factory()->create(['name' => 'Editor User']);

    $day = EmployeeAttendanceDay::factory()->create([
        'original_entry_source' => AttendanceEntrySource::Device,
        'last_modified_source' => AttendanceEntrySource::Manual,
        'created_by_user_id' => null,
        'updated_by_user_id' => $editor->id,
        'ingest_key' => 'ATD-20260101-000001',
    ]);

    $day->employee->forceFill(['attendance_id' => 'BIO-PRO-1'])->save();

    $day->refresh();

    $today = now()->toDateString();
    $day->load([
        'employee' => function ($query) use ($today): void {
            (TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForAttendancePresenters($today))($query);
        },
        'organizationalUnit.unitType',
        'segments',
        'workScheduleTemplate',
        'createdByUser.employee',
        'updatedByUser.employee',
    ]);

    $row = TeamAttendanceRecordPresenter::toPageRow($day);

    expect($row['original_source'])->toBe('device')
        ->and($row['last_modified_source'])->toBe('manual')
        ->and($row['created_by'])->toBeNull()
        ->and($row['updated_by'])->toBe('Editor User')
        ->and($row['attendance_id'])->toBe('BIO-PRO-1')
        ->and($row['ingest_key'])->toBe('ATD-20260101-000001');

    expect($row)->toHaveKeys(['created_at', 'updated_at'])
        ->and($row['created_at'])->toBeString()
        ->and($row['updated_at'])->toBeString();
});

test('team attendance presenter fills unit from primary assignment when day unit is null', function (): void {
    $organization = Organization::factory()->create();
    $unit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Demo Branch Unit',
    ]);
    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $employee = Employee::factory()->create();

    EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $unit->id,
        'is_primary' => true,
    ]);

    $day = EmployeeAttendanceDay::factory()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'work_schedule_template_id' => $template->id,
        'organizational_unit_id' => null,
    ]);

    $today = now()->toDateString();
    $day->load([
        'employee' => function ($query) use ($today): void {
            (TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForAttendancePresenters($today))($query);
        },
        'organizationalUnit.unitType',
        'segments',
        'workScheduleTemplate',
    ]);

    $row = TeamAttendanceRecordPresenter::toPageRow($day);

    expect($row['organizational_unit_id'])->toBeNull()
        ->and($row['unit_name'])->toBe('Demo Branch Unit')
        ->and($row['unit_filter_value'])->toBe('unit-'.$unit->id);
});
