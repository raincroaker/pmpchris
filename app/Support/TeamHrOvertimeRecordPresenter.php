<?php

namespace App\Support;

use App\Models\EmployeeOvertime;

final class TeamHrOvertimeRecordPresenter
{
    /**
     * Shape consumed by [`Overtime/Team.vue`](resources/js/pages/Overtime/Team.vue) (`TeamOvertimeRow`).
     *
     * @return array<string, mixed>
     */
    public static function toPageRow(EmployeeOvertime $overtime): array
    {
        $employee = $overtime->employee;
        $policy = $overtime->overtimePolicy;
        $approver = $overtime->approver;
        $unit = $overtime->organizationalUnit;
        $uid = $overtime->organizational_unit_id;

        return [
            'id' => (int) $overtime->id,
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
            'ot_date' => $overtime->ot_date->format('Y-m-d'),
            'hours' => (float) $overtime->hours,
            'context' => $policy->context->value,
            'policy_code' => (string) $policy->code,
            'rate_multiplier' => (float) $policy->rate_multiplier,
            'status' => $overtime->status->value,
            'submitted_at' => $overtime->submitted_at->format('Y-m-d'),
            'decided_at' => $overtime->decided_at?->format('Y-m-d'),
            'approver_employee_id' => (int) $overtime->approver_employee_id,
            'approver_name' => TeamHrEmployeeDisplay::fullName($approver),
            'approver_id_number' => (string) $approver->id_number,
            'reason' => $overtime->reason !== null && $overtime->reason !== '' ? (string) $overtime->reason : null,
        ];
    }
}
