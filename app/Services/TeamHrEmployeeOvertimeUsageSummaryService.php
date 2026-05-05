<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\OvertimePolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class TeamHrEmployeeOvertimeUsageSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function build(
        int $organizationId,
        int $employeeId,
        string $policyCode,
        ?int $excludeEmployeeOvertimeId,
        CarbonImmutable $asOf,
    ): array {
        $policyCode = trim($policyCode);
        $asOfDay = $asOf->startOfDay();
        $year = $asOfDay->year;
        $weekStartYmd = $asOfDay->startOfWeek(CarbonInterface::MONDAY)->format('Y-m-d');
        $weekEndYmd = $asOfDay->startOfWeek(CarbonInterface::MONDAY)->endOfWeek(CarbonInterface::SUNDAY)->format('Y-m-d');
        $monthStartYmd = $asOfDay->startOfMonth()->format('Y-m-d');
        $monthEndYmd = $asOfDay->startOfMonth()->endOfMonth()->format('Y-m-d');

        $policy = OvertimePolicy::query()
            ->where('organization_id', $organizationId)
            ->where('code', $policyCode)
            ->where('deleted_at', null)
            ->first();

        $rows = DB::table('employee_overtimes as eo')
            ->join('overtime_policies as op', 'eo.overtime_policy_id', '=', 'op.id')
            ->where('eo.organization_id', $organizationId)
            ->where('eo.employee_id', $employeeId)
            ->whereNull('eo.deleted_at')
            ->where('eo.status', EmployeeHrRecordStatus::Approved->value)
            ->whereNull('op.deleted_at')
            ->when($excludeEmployeeOvertimeId !== null, function ($q) use ($excludeEmployeeOvertimeId): void {
                $q->where('eo.id', '!=', $excludeEmployeeOvertimeId);
            })
            ->whereYear('eo.ot_date', $year)
            ->orderBy('eo.ot_date')
            ->orderBy('eo.id')
            /** @phpstan-ignore return.type */
            ->get([
                'eo.id',
                'eo.ot_date',
                'eo.hours',
                'op.code as policy_code',
                'op.name as policy_name',
            ]);

        /** @var array<string, array{name: string, hours_week: float, hours_month: float, hours_year: float, ids_week: array<int, bool>, ids_month: array<int, bool>, ids_year: array<int, bool>}> $bucket */
        $bucket = [];

        foreach ($rows as $row) {
            $code = (string) $row->policy_code;
            $hours = (float) $row->hours;
            $otYmd = CarbonImmutable::parse((string) $row->ot_date)->format('Y-m-d');
            $id = (int) $row->id;
            $name = (string) $row->policy_name;

            if (! isset($bucket[$code])) {
                $bucket[$code] = [
                    'name' => $name,
                    'hours_week' => 0.0,
                    'hours_month' => 0.0,
                    'hours_year' => 0.0,
                    'ids_week' => [],
                    'ids_month' => [],
                    'ids_year' => [],
                ];
            }

            $bucket[$code]['hours_year'] += $hours;
            $bucket[$code]['ids_year'][$id] = true;

            if ($otYmd >= $monthStartYmd && $otYmd <= $monthEndYmd) {
                $bucket[$code]['hours_month'] += $hours;
                $bucket[$code]['ids_month'][$id] = true;
            }

            if ($otYmd >= $weekStartYmd && $otYmd <= $weekEndYmd) {
                $bucket[$code]['hours_week'] += $hours;
                $bucket[$code]['ids_week'][$id] = true;
            }
        }

        $approvedByPolicy = collect($bucket)
            ->map(fn (array $p, string $code): array => [
                'code' => $code,
                'name' => $p['name'],
                'approved_hours_week' => round($p['hours_week'], 2),
                'approved_hours_month' => round($p['hours_month'], 2),
                'approved_hours_year' => round($p['hours_year'], 2),
                'approved_records_distinct_week' => count($p['ids_week']),
                'approved_records_distinct_month' => count($p['ids_month']),
                'approved_records_distinct_year' => count($p['ids_year']),
            ])
            ->values()
            ->sortByDesc(static fn (array $r): float => (float) $r['approved_hours_year'])
            ->values()
            ->all();

        $samePolicy = collect($approvedByPolicy)->firstWhere('code', $policyCode);
        $samePolicySummary = [
            'approved_hours_week' => round((float) ($samePolicy['approved_hours_week'] ?? 0), 2),
            'approved_hours_month' => round((float) ($samePolicy['approved_hours_month'] ?? 0), 2),
            'approved_hours_year' => round((float) ($samePolicy['approved_hours_year'] ?? 0), 2),
            'approved_records_distinct_week' => (int) ($samePolicy['approved_records_distinct_week'] ?? 0),
            'approved_records_distinct_month' => (int) ($samePolicy['approved_records_distinct_month'] ?? 0),
            'approved_records_distinct_year' => (int) ($samePolicy['approved_records_distinct_year'] ?? 0),
        ];

        return [
            'reference' => [
                'as_of' => $asOfDay->format('Y-m-d'),
                'tz' => (string) config('app.timezone'),
                'week_start' => $weekStartYmd,
                'week_end' => $weekEndYmd,
                'month_label' => $asOfDay->format('F Y'),
                'year' => $year,
            ],
            'same_policy_code' => $samePolicySummary,
            'approved_by_policy' => $approvedByPolicy,
            'overtime_policy' => $policy instanceof OvertimePolicy
                ? [
                    'code' => $policy->code,
                    'name' => $policy->name,
                    'context' => $policy->context->value,
                    'rate_multiplier' => (float) $policy->rate_multiplier,
                    'weekly_cap_hours' => $policy->weekly_cap_hours !== null ? (float) $policy->weekly_cap_hours : null,
                    'daily_cap_hours' => $policy->daily_cap_hours !== null ? (float) $policy->daily_cap_hours : null,
                ]
                : null,
        ];
    }
}
