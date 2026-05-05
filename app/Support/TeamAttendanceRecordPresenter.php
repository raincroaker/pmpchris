<?php

namespace App\Support;

use App\Enums\AttendanceEntrySource;
use App\Enums\WorkScheduleClockPattern;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use App\Models\OrganizationalUnit;
use App\Models\User;

final class TeamAttendanceRecordPresenter
{
    /**
     * Shape consumed by [`Attendance/Team.vue`](resources/js/pages/Attendance/Team.vue) (`TeamAttendanceRow`).
     *
     * @return array<string, mixed>
     */
    public static function toPageRow(EmployeeAttendanceDay $day): array
    {
        $employee = $day->employee;
        $template = $day->workScheduleTemplate;

        $displayUnit = $day->organizationalUnit ?? self::fallbackOrganizationalUnitFromAssignments($employee);
        $displayUnitId = $displayUnit?->id;
        $storedUnitId = $day->organizational_unit_id;

        $punctuality = self::punctualityForPage($day);

        return [
            'id' => (int) $day->id,
            'employee' => [
                'display_name' => TeamHrEmployeeDisplay::fullName($employee),
                'id_number' => (string) $employee->id_number,
                'avatar_url' => TeamHrEmployeeDisplay::avatarUrl($employee->user),
            ],
            'employee_record_id' => (int) $employee->id,
            'organizational_unit_id' => $storedUnitId !== null ? (int) $storedUnitId : null,
            'unit_filter_value' => $displayUnitId !== null ? 'unit-'.(int) $displayUnitId : '',
            'unit_name' => $displayUnit !== null ? (string) $displayUnit->name : '',
            'unit_code' => $displayUnit?->code !== null && $displayUnit->code !== '' ? (string) $displayUnit->code : null,
            'placement_unit_type_color' => TeamHrEmployeeDirectoryExtras::unitTypeHexColor($displayUnit),
            'placement_is_primary' => TeamHrEmployeeDirectoryExtras::primaryPlacementOnUnit(
                $employee,
                $displayUnitId !== null ? (int) $displayUnitId : null,
            ),
            'placement_unit_type' => $displayUnit?->unitType?->name,
            'positions' => TeamHrEmployeeDirectoryExtras::mapActivePositions($employee),
            'work_date' => $day->work_date->format('Y-m-d'),
            'attendance_id' => self::employeeProfileAttendanceId($employee),
            'ingest_key' => self::ingestKeyDisplay($day),
            'clock_pattern' => $day->clock_pattern instanceof WorkScheduleClockPattern
                ? $day->clock_pattern->value
                : (string) $day->clock_pattern,
            'is_overnight_schedule' => (bool) $day->is_overnight_schedule,
            'work_schedule_template_id' => (int) $day->work_schedule_template_id,
            'work_schedule_name' => $template !== null ? (string) $template->name : '',
            'unpaid_break_minutes' => $template !== null ? (int) $template->unpaid_break_minutes : 0,
            'segments' => $day->segments->map(static fn (EmployeeAttendanceSegment $s): array => [
                'label' => (string) ($s->label ?? ''),
                'scheduled_in' => (string) $s->scheduled_in,
                'scheduled_out' => (string) $s->scheduled_out,
                'actual_in' => $s->actual_in !== null && $s->actual_in !== '' ? (string) $s->actual_in : null,
                'actual_out' => $s->actual_out !== null && $s->actual_out !== '' ? (string) $s->actual_out : null,
            ])->values()->all(),
            'net_hours' => (float) ($day->net_hours ?? 0),
            'variance_label' => $day->variance_label !== null && $day->variance_label !== ''
                ? (string) $day->variance_label
                : '',
            'punctuality' => $punctuality,
            'status' => $day->status->value,
            'original_source' => self::sourceForPage($day->original_entry_source),
            'last_modified_source' => self::sourceForPage($day->last_modified_source),
            'created_by' => self::userDisplayForAttendanceAudit($day->createdByUser),
            'created_at' => $day->created_at->toIso8601String(),
            'updated_at' => $day->updated_at !== null
                ? $day->updated_at->toIso8601String()
                : $day->created_at->toIso8601String(),
            'updated_by' => self::userDisplayForAttendanceAudit($day->updatedByUser),
        ];
    }

    /**
     * {@see Employee::$attendance_id} (parity with Employee Schedules), trimmed or null.
     */
    private static function employeeProfileAttendanceId(Employee $employee): ?string
    {
        $raw = $employee->attendance_id;
        if (! is_string($raw)) {
            return null;
        }

        $t = trim($raw);

        return $t !== '' ? $t : null;
    }

    /**
     * Optional per-day device/import key (distinct from profile {@see Employee::$attendance_id}).
     */
    private static function ingestKeyDisplay(EmployeeAttendanceDay $day): ?string
    {
        $raw = $day->ingest_key;
        if (! is_string($raw)) {
            return null;
        }

        $t = trim($raw);

        return $t !== '' ? $t : null;
    }

    /**
     * When {@see EmployeeAttendanceDay::$organizational_unit_id} is unset, use the employee’s
     * primary unit assignment (if any) so the directory chip matches Employee Schedules.
     */
    private static function fallbackOrganizationalUnitFromAssignments(Employee $employee): ?OrganizationalUnit
    {
        if (! $employee->relationLoaded('assignments')) {
            return null;
        }

        $withUnit = $employee->assignments->filter(
            fn (EmployeeAssignment $a): bool => $a->organizational_unit_id !== null
                && $a->relationLoaded('organizationalUnit')
                && $a->organizationalUnit !== null,
        );

        if ($withUnit->isEmpty()) {
            return null;
        }

        $primary = $withUnit->firstWhere('is_primary', true);
        $pick = $primary ?? $withUnit->sortBy(fn (EmployeeAssignment $a): int => (int) $a->id)->first();

        return $pick?->organizationalUnit;
    }

    private static function userDisplayForAttendanceAudit(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->relationLoaded('employee') && $user->employee !== null) {
            return TeamHrEmployeeDisplay::fullName($user->employee);
        }

        $name = $user->name;

        return $name !== '' ? (string) $name : null;
    }

    /**
     * @return 'on_time'|'late'|'not_applicable'
     */
    public static function punctualityForPage(EmployeeAttendanceDay $day): string
    {
        $raw = $day->getAttributes()['punctuality'] ?? null;
        if ($raw === null || $raw === '') {
            return 'not_applicable';
        }

        $s = (string) $raw;

        return match ($s) {
            'on_time', 'late' => $s,
            default => 'not_applicable',
        };
    }

    /**
     * @return 'device'|'manual'|'import'
     */
    public static function sourceForPage(AttendanceEntrySource $source): string
    {
        return match ($source) {
            AttendanceEntrySource::Device => 'device',
            AttendanceEntrySource::Manual => 'manual',
            AttendanceEntrySource::Import => 'import',
        };
    }
}
