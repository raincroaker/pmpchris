<?php

namespace App\Support;

use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveDay;
use DateTimeImmutable;

final class TeamHrLeaveRecordPresenter
{
    /**
     * Shape consumed by [`Leave/Team.vue`](resources/js/pages/Leave/Team.vue) (`TeamLeaveRow`).
     *
     * @return array<string, mixed>
     */
    public static function toPageRow(EmployeeLeave $leave): array
    {
        $employee = $leave->employee;
        $policy = $leave->leavePolicy;
        $approver = $leave->approver;
        $unit = $leave->organizationalUnit;
        $uid = $leave->organizational_unit_id;

        return [
            'id' => (int) $leave->id,
            'employee' => [
                'display_name' => TeamHrEmployeeDisplay::fullName($employee),
                'id_number' => (string) $employee->id_number,
                'avatar_url' => TeamHrEmployeeDisplay::avatarUrl($employee->user),
            ],
            'employee_record_id' => (int) $employee->id,
            'unit_filter_value' => $uid !== null ? 'unit-'.(int) $uid : '',
            'organizational_unit_id' => $uid !== null ? (int) $uid : null,
            'unit_name' => $unit !== null ? (string) $unit->name : '',
            'unit_code' => $unit?->code !== null && $unit->code !== '' ? (string) $unit->code : null,
            'unit_type' => $unit?->unitType?->name,
            'unit_type_color' => TeamHrEmployeeDirectoryExtras::unitTypeHexColor($unit),
            'unit_is_primary' => TeamHrEmployeeDirectoryExtras::primaryPlacementOnUnit(
                $employee,
                $uid !== null ? (int) $uid : null,
            ),
            'positions' => TeamHrEmployeeDirectoryExtras::mapActivePositions($employee),
            'leave_type_code' => (string) $policy->code,
            'leave_type_name' => (string) $policy->name,
            'start_date' => $leave->start_date->format('Y-m-d'),
            'end_date' => $leave->end_date->format('Y-m-d'),
            'is_half_day_start' => (bool) $leave->is_half_day_start,
            'is_half_day_end' => (bool) $leave->is_half_day_end,
            'leave_days' => self::leaveDaysForPageRow($leave),
            'duration_label' => self::durationLabel($leave),
            'status' => $leave->status->value,
            'submitted_at' => $leave->submitted_at->format('Y-m-d'),
            'decided_at' => $leave->decided_at?->format('Y-m-d'),
            'approver_employee_id' => (int) $leave->approver_employee_id,
            'approver_name' => TeamHrEmployeeDisplay::fullName($approver),
            'approver_id_number' => (string) $approver->id_number,
            'reason' => $leave->reason !== null && $leave->reason !== '' ? (string) $leave->reason : null,
        ];
    }

    /**
     * @return list<array{date: string, is_half_day: bool}>
     */
    public static function leaveDaysForPageRow(EmployeeLeave $leave): array
    {
        if ($leave->relationLoaded('leaveDays') && $leave->leaveDays->isNotEmpty()) {
            return $leave->leaveDays
                ->sortBy(static fn (EmployeeLeaveDay $d): string => $d->leave_date->format('Y-m-d'))
                ->values()
                ->map(static fn (EmployeeLeaveDay $d): array => [
                    'date' => $d->leave_date->format('Y-m-d'),
                    'is_half_day' => (bool) $d->is_half_day,
                ])
                ->all();
        }

        return array_values(array_map(
            static fn (array $row): array => [
                'date' => $row['leave_date'],
                'is_half_day' => $row['is_half_day'],
            ],
            EmployeeLeaveLegacyDayExpansion::expandFromLegacySpan(
                $leave->start_date,
                $leave->end_date,
                $leave->is_half_day_start,
                $leave->is_half_day_end,
            ),
        ));
    }

    public static function durationLabel(EmployeeLeave $leave): string
    {
        $start = new DateTimeImmutable($leave->start_date->format('Y-m-d'));
        $end = new DateTimeImmutable($leave->end_date->format('Y-m-d'));
        $days = (int) $start->diff($end)->days + 1;
        $label = $days === 1 ? '1 day' : "{$days} days";
        if ($leave->is_half_day_start) {
            $label .= ' · start ½';
        }
        if ($leave->is_half_day_end) {
            $label .= ' · end ½';
        }

        return $label;
    }
}
