<?php

namespace Database\Seeders;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveDay;
use App\Models\EmployeeOvertime;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\OvertimePolicy;
use App\Support\EmployeeLeaveLegacyDayExpansion;
use Illuminate\Database\Seeder;

class DevelopmentTeamHrMay2026LeaveOvertimeSeeder extends Seeder
{
    /**
     * Marker stored in {@see EmployeeLeave::$reason} / {@see EmployeeOvertime::$reason} for idempotent re-runs.
     */
    private const SEED_REASON = 'May 2026 dev seed (Team HR demo).';

    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $orgId = (int) $organization->id;

        $this->purgePriorSeedRows($orgId);

        $emp008 = Employee::query()->where('id_number', 'EMP-SEED-008')->first();
        $emp005 = Employee::query()->where('id_number', 'EMP-SEED-005')->first();
        $emp003 = Employee::query()->where('id_number', 'EMP-SEED-003')->first();

        if ($emp008 === null || $emp005 === null || $emp003 === null) {
            return;
        }

        $unit008 = OrganizationalUnit::query()
            ->where('organization_id', $orgId)
            ->where('code', 'PAN-D2-S1')
            ->first();
        $unit005 = OrganizationalUnit::query()
            ->where('organization_id', $orgId)
            ->where('code', 'PAN-D1')
            ->first();

        if ($unit008 === null || $unit005 === null) {
            return;
        }

        $vl = LeavePolicy::query()
            ->where('organization_id', $orgId)
            ->where('code', 'VL')
            ->whereNull('deleted_at')
            ->first();
        $sl = LeavePolicy::query()
            ->where('organization_id', $orgId)
            ->where('code', 'SL')
            ->whereNull('deleted_at')
            ->first();
        $otWd = OvertimePolicy::query()
            ->where('organization_id', $orgId)
            ->where('code', 'OT-WD')
            ->whereNull('deleted_at')
            ->first();
        $otRd = OvertimePolicy::query()
            ->where('organization_id', $orgId)
            ->where('code', 'OT-RD')
            ->whereNull('deleted_at')
            ->first();

        if ($vl === null || $sl === null || $otWd === null || $otRd === null) {
            return;
        }

        $leavePreMayOne = EmployeeLeave::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp008->id,
            'organizational_unit_id' => $unit008->id,
            'leave_policy_id' => $vl->id,
            'start_date' => '2026-01-22',
            'end_date' => '2026-01-23',
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-01-17',
            'decided_at' => '2026-01-18',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
        $this->seedLeaveDaysForLeave($leavePreMayOne);

        $leavePreMayTwo = EmployeeLeave::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp005->id,
            'organizational_unit_id' => $unit005->id,
            'leave_policy_id' => $sl->id,
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-10',
            'is_half_day_start' => true,
            'is_half_day_end' => true,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-02-06',
            'decided_at' => '2026-02-07',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
        $this->seedLeaveDaysForLeave($leavePreMayTwo);

        $leavePreMayThree = EmployeeLeave::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp008->id,
            'organizational_unit_id' => $unit008->id,
            'leave_policy_id' => $vl->id,
            'start_date' => '2026-03-18',
            'end_date' => '2026-03-18',
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'status' => EmployeeHrRecordStatus::Rejected,
            'submitted_at' => '2026-03-15',
            'decided_at' => '2026-03-16',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
        $this->seedLeaveDaysForLeave($leavePreMayThree);

        $leavePreMayFour = EmployeeLeave::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp005->id,
            'organizational_unit_id' => $unit005->id,
            'leave_policy_id' => $sl->id,
            'start_date' => '2026-04-07',
            'end_date' => '2026-04-08',
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-04-02',
            'decided_at' => '2026-04-03',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
        $this->seedLeaveDaysForLeave($leavePreMayFour);

        EmployeeOvertime::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp008->id,
            'organizational_unit_id' => $unit008->id,
            'overtime_policy_id' => $otWd->id,
            'ot_date' => '2026-01-29',
            'hours' => 2,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-01-28',
            'decided_at' => '2026-01-28',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);

        EmployeeOvertime::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp005->id,
            'organizational_unit_id' => $unit005->id,
            'overtime_policy_id' => $otRd->id,
            'ot_date' => '2026-02-22',
            'hours' => 3,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-02-21',
            'decided_at' => '2026-02-21',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);

        EmployeeOvertime::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp005->id,
            'organizational_unit_id' => $unit005->id,
            'overtime_policy_id' => $otWd->id,
            'ot_date' => '2026-03-10',
            'hours' => 2,
            'status' => EmployeeHrRecordStatus::Rejected,
            'submitted_at' => '2026-03-09',
            'decided_at' => '2026-03-09',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);

        EmployeeOvertime::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp008->id,
            'organizational_unit_id' => $unit008->id,
            'overtime_policy_id' => $otWd->id,
            'ot_date' => '2026-04-15',
            'hours' => 1,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-04-14',
            'decided_at' => '2026-04-14',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);

        $leave1 = EmployeeLeave::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp008->id,
            'organizational_unit_id' => $unit008->id,
            'leave_policy_id' => $vl->id,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-14',
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-05-04',
            'decided_at' => '2026-05-05',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
        $this->seedLeaveDaysForLeave($leave1);

        /** Single day with ½ (matches explicit-day UI demo). */
        $leave2 = EmployeeLeave::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp005->id,
            'organizational_unit_id' => $unit005->id,
            'leave_policy_id' => $sl->id,
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-20',
            'is_half_day_start' => true,
            'is_half_day_end' => true,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-05-06',
            'decided_at' => '2026-05-07',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
        $this->seedLeaveDaysForLeave($leave2);

        EmployeeOvertime::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp008->id,
            'organizational_unit_id' => $unit008->id,
            'overtime_policy_id' => $otWd->id,
            'ot_date' => '2026-05-06',
            'hours' => 2,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-05-04',
            'decided_at' => '2026-05-05',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);

        EmployeeOvertime::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp005->id,
            'organizational_unit_id' => $unit005->id,
            'overtime_policy_id' => $otRd->id,
            'ot_date' => '2026-05-03',
            'hours' => 4,
            'status' => EmployeeHrRecordStatus::Approved,
            'submitted_at' => '2026-05-02',
            'decided_at' => '2026-05-02',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);

        $leave3 = EmployeeLeave::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp008->id,
            'organizational_unit_id' => $unit008->id,
            'leave_policy_id' => $vl->id,
            'start_date' => '2026-05-27',
            'end_date' => '2026-05-27',
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'status' => EmployeeHrRecordStatus::Rejected,
            'submitted_at' => '2026-05-18',
            'decided_at' => '2026-05-19',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
        $this->seedLeaveDaysForLeave($leave3);

        EmployeeOvertime::query()->create([
            'organization_id' => $orgId,
            'employee_id' => $emp005->id,
            'organizational_unit_id' => $unit005->id,
            'overtime_policy_id' => $otWd->id,
            'ot_date' => '2026-05-15',
            'hours' => 3,
            'status' => EmployeeHrRecordStatus::Rejected,
            'submitted_at' => '2026-05-12',
            'decided_at' => '2026-05-13',
            'approver_employee_id' => $emp003->id,
            'reason' => self::SEED_REASON,
        ]);
    }

    private function purgePriorSeedRows(int $organizationId): void
    {
        $leaveIds = EmployeeLeave::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->where('reason', self::SEED_REASON)
            ->pluck('id');

        if ($leaveIds->isNotEmpty()) {
            EmployeeLeaveDay::query()
                ->whereIn('employee_leave_id', $leaveIds->all())
                ->delete();
        }

        EmployeeLeave::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->where('reason', self::SEED_REASON)
            ->forceDelete();

        EmployeeOvertime::query()
            ->where('organization_id', $organizationId)
            ->where('reason', self::SEED_REASON)
            ->delete();
    }

    private function seedLeaveDaysForLeave(EmployeeLeave $leave): void
    {
        $days = EmployeeLeaveLegacyDayExpansion::expandFromLegacySpan(
            $leave->start_date,
            $leave->end_date,
            (bool) $leave->is_half_day_start,
            (bool) $leave->is_half_day_end,
        );

        foreach ($days as $day) {
            EmployeeLeaveDay::query()->create([
                'organization_id' => $leave->organization_id,
                'employee_id' => $leave->employee_id,
                'employee_leave_id' => $leave->id,
                'leave_date' => $day['leave_date'],
                'is_half_day' => $day['is_half_day'],
            ]);
        }
    }
}
