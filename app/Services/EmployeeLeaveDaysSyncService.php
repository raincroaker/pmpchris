<?php

namespace App\Services;

use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveDay;
use App\Support\EmployeeLeaveLegacyDayExpansion;
use Illuminate\Support\Facades\DB;

final readonly class EmployeeLeaveDaysSyncService
{
    /**
     * Persist explicit leave days when the payload includes {@code leave_days}; otherwise expand from legacy span headers.
     *
     * @param  list<array{date: string, is_half_day?: bool}>|null  $leaveDaysPayload
     */
    public function sync(EmployeeLeave $leave, ?array $leaveDaysPayload): void
    {
        $rows = $this->resolvedLeaveDayRows($leave, $leaveDaysPayload);

        DB::transaction(function () use ($leave, $rows): void {
            EmployeeLeaveDay::query()
                ->where('employee_leave_id', $leave->id)
                ->delete();

            if ($rows === []) {
                return;
            }

            $now = now();

            EmployeeLeaveDay::query()->insert(
                array_map(
                    fn (array $row): array => [
                        'organization_id' => (int) $leave->organization_id,
                        'employee_id' => (int) $leave->employee_id,
                        'employee_leave_id' => (int) $leave->id,
                        'leave_date' => $row['leave_date'],
                        'is_half_day' => $row['is_half_day'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $rows,
                ),
            );
        });
    }

    /**
     * @param  list<array{date: string, is_half_day?: bool}>|null  $leaveDaysPayload
     * @return list<array{leave_date: string, is_half_day: bool}>
     */
    private function resolvedLeaveDayRows(EmployeeLeave $leave, ?array $leaveDaysPayload): array
    {
        if ($leaveDaysPayload !== null && count($leaveDaysPayload) > 0) {
            /** @var list<array{leave_date: string, is_half_day: bool}> $out */
            $out = [];

            foreach ($leaveDaysPayload as $entry) {
                $iso = substr((string) ($entry['date'] ?? ''), 0, 10);
                if ($iso === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
                    continue;
                }

                $out[] = [
                    'leave_date' => $iso,
                    'is_half_day' => (bool) ($entry['is_half_day'] ?? false),
                ];
            }

            usort(
                $out,
                fn (array $a, array $b): int => strcmp($a['leave_date'], $b['leave_date']),
            );

            return $out;
        }

        return EmployeeLeaveLegacyDayExpansion::expandFromLegacySpan(
            $leave->start_date,
            $leave->end_date,
            $leave->is_half_day_start,
            $leave->is_half_day_end,
        );
    }
}
