<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\LeavePolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class TeamHrEmployeeLeaveUsageSummaryService
{
    /**
     * @param  list<string>  $draftDatesYmd
     * @return array<string, mixed>
     */
    public function build(
        int $organizationId,
        int $employeeId,
        string $leaveTypeCode,
        ?int $excludeEmployeeLeaveId,
        array $draftDatesYmd,
        CarbonImmutable $asOf,
    ): array {
        $leaveTypeCode = trim($leaveTypeCode);
        $asOfDay = $asOf->startOfDay();
        $year = $asOfDay->year;

        $monthStartYmd = $asOfDay->startOfMonth()->format('Y-m-d');
        $monthEndYmd = $asOfDay->startOfMonth()->endOfMonth()->format('Y-m-d');
        $weekStartYmd = $asOfDay->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d');
        $weekEndYmd = $asOfDay->startOfWeek(CarbonInterface::MONDAY)->endOfWeek(CarbonInterface::SUNDAY)->format('Y-m-d');
        $policy = LeavePolicy::query()
            ->where('organization_id', $organizationId)
            ->where('code', $leaveTypeCode)
            ->where('deleted_at', null)
            ->first();

        $baseRows = DB::table('employee_leave_days as eld')
            ->join('employee_leaves as el', 'eld.employee_leave_id', '=', 'el.id')
            ->join('leave_policies as lp', static function (JoinClause $join): void {
                $join->on('el.leave_policy_id', '=', 'lp.id')
                    ->whereNull('lp.deleted_at');
            })
            ->where('eld.organization_id', $organizationId)
            ->where('eld.employee_id', $employeeId)
            ->whereNull('el.deleted_at')
            ->where('el.status', EmployeeHrRecordStatus::Approved->value)
            ->when($excludeEmployeeLeaveId !== null, function ($q) use ($excludeEmployeeLeaveId) {
                $q->where('el.id', '!=', $excludeEmployeeLeaveId);
            })
            ->whereYear('eld.leave_date', $year)
            ->orderBy('eld.leave_date')
            ->orderBy('eld.id')
            /** @phpstan-ignore return.type */
            ->get([
                'eld.leave_date',
                'eld.is_half_day',
                'eld.employee_leave_id',
                'lp.code as policy_code',
                'lp.name as policy_name',
            ]);

        /** @var array<string, array{name: string, units_week: float, units_month: float, units_year: float, ids_week: array<int, bool>, ids_month: array<int, bool>, ids_year: array<int, bool>}> $bucket */
        $bucket = [];

        foreach ($baseRows as $row) {
            $code = (string) $row->policy_code;
            $rawHalf = $row->is_half_day;
            $isHalfDay = ($rawHalf === true || $rawHalf === '1' || $rawHalf === 1 || $rawHalf === 'true');
            $units = $isHalfDay ? 0.5 : 1.0;

            $leaveYmd = CarbonImmutable::parse((string) $row->leave_date)->format('Y-m-d');
            $leavePk = (int) $row->employee_leave_id;
            $name = (string) $row->policy_name;

            if (! isset($bucket[$code])) {
                $bucket[$code] = [
                    'name' => $name,
                    'units_week' => 0.0,
                    'units_month' => 0.0,
                    'units_year' => 0.0,
                    'ids_week' => [],
                    'ids_month' => [],
                    'ids_year' => [],
                ];
            }

            $bucket[$code]['units_year'] += $units;
            $bucket[$code]['ids_year'][$leavePk] = true;

            if ($leaveYmd >= $weekStartYmd && $leaveYmd <= $weekEndYmd) {
                $bucket[$code]['units_week'] += $units;
                $bucket[$code]['ids_week'][$leavePk] = true;
            }

            if ($leaveYmd >= $monthStartYmd && $leaveYmd <= $monthEndYmd) {
                $bucket[$code]['units_month'] += $units;
                $bucket[$code]['ids_month'][$leavePk] = true;
            }
        }

        $approvedByType = collect($bucket)
            ->map(fn (array $p, string $code): array => [
                'code' => $code,
                'name' => $p['name'],
                'leave_units_week' => round($p['units_week'], 4),
                'leave_units_month' => round($p['units_month'], 4),
                'leave_units_year' => round($p['units_year'], 4),
                'records_week' => count($p['ids_week']),
                'records_month' => count($p['ids_month']),
                'records_year' => count($p['ids_year']),
            ])
            ->values()
            ->sortByDesc(static fn (array $r): float => $r['leave_units_year'])
            ->values()
            ->all();

        $sameLeaveType = collect($approvedByType)->firstWhere('code', $leaveTypeCode);

        $sameLeaveTyped = [
            'approved_leave_units_week' => round((float) ($sameLeaveType['leave_units_week'] ?? 0), 4),
            'approved_leave_units_month' => round((float) ($sameLeaveType['leave_units_month'] ?? 0), 4),
            'approved_leave_units_year' => round((float) ($sameLeaveType['leave_units_year'] ?? 0), 4),
            'approved_records_distinct_week' => (int) ($sameLeaveType['records_week'] ?? 0),
            'approved_records_distinct_month' => (int) ($sameLeaveType['records_month'] ?? 0),
            'approved_records_distinct_year' => (int) ($sameLeaveType['records_year'] ?? 0),
        ];

        /** @var list<string> */
        $uniqueDraftDates = array_values(array_unique(array_filter(array_map(static function ($d): string {
            return substr(trim((string) $d), 0, 10);
        }, $draftDatesYmd), static fn (string $d): bool => $d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1)));

        sort($uniqueDraftDates);

        /** @var list<array{date: string, employee_leave_id: int, leave_type_code: string, leave_type_name: string}> */
        $overlapRows = [];

        if ($uniqueDraftDates !== []) {
            $overlapHits = DB::table('employee_leave_days as eld')
                ->join('employee_leaves as el', 'eld.employee_leave_id', '=', 'el.id')
                ->join('leave_policies as lp', static function (JoinClause $join): void {
                    $join->on('el.leave_policy_id', '=', 'lp.id')
                        ->whereNull('lp.deleted_at');
                })
                ->where('eld.organization_id', $organizationId)
                ->where('eld.employee_id', $employeeId)
                ->whereNull('el.deleted_at')
                ->where('el.status', EmployeeHrRecordStatus::Approved->value)
                ->when($excludeEmployeeLeaveId !== null, function ($q) use ($excludeEmployeeLeaveId) {
                    $q->where('el.id', '!=', $excludeEmployeeLeaveId);
                })
                ->where(static function ($w) use ($uniqueDraftDates): void {
                    foreach ($uniqueDraftDates as $i => $iso) {
                        if ($i === 0) {
                            /** @phpstan-ignore arguments.count */
                            $w->whereDate('eld.leave_date', '=', $iso);

                            continue;
                        }
                        /** @phpstan-ignore arguments.count */
                        $w->orWhereDate('eld.leave_date', '=', $iso);
                    }
                })
                /** @phpstan-ignore return.type */
                ->select([
                    'eld.leave_date',
                    'eld.employee_leave_id',
                    'lp.code',
                    'lp.name',
                ])
                ->orderBy('eld.leave_date')
                ->get();

            foreach ($overlapHits as $hit) {
                $overlapRows[] = [
                    'date' => CarbonImmutable::parse((string) $hit->leave_date)->format('Y-m-d'),
                    'employee_leave_id' => (int) $hit->employee_leave_id,
                    'leave_type_code' => (string) $hit->code,
                    'leave_type_name' => (string) $hit->name,
                ];
            }
        }

        $leavePolicyPayload = null;

        if ($policy instanceof LeavePolicy) {
            $leavePolicyPayload = [
                'code' => $policy->code,
                'name' => $policy->name,
                'unit' => $policy->unit->value,
                'annual_entitlement' => $policy->annual_entitlement !== null
                    ? (string) $policy->annual_entitlement
                    : null,
            ];
        }

        return [
            'reference' => [
                'as_of' => $asOfDay->format('Y-m-d'),
                'tz' => (string) config('app.timezone'),
                'week_start' => $asOfDay->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d'),
                'week_end' => $asOfDay->startOfWeek(CarbonInterface::MONDAY)->endOfWeek(CarbonInterface::SUNDAY)->format('Y-m-d'),
                'month_label' => $asOfDay->format('F Y'),
                'year' => $year,
            ],
            'same_leave_type_code' => $sameLeaveTyped,
            'approved_by_type' => $approvedByType,
            'overlaps' => $overlapRows,
            'leave_policy' => $leavePolicyPayload,
            'uses_counted_leave_days_only' => true,
        ];
    }
}
