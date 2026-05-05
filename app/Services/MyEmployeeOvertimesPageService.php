<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Http\Requests\IndexMyEmployeeOvertimesRequest;
use App\Models\EmployeeOvertime;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use App\Support\TeamHrEmployeeDirectoryExtras;
use App\Support\TeamHrOvertimeRecordPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class MyEmployeeOvertimesPageService
{
    private const PREVIEW_LIMIT = 100;

    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * @return array{
     *     paginator: LengthAwarePaginator<int, array<string, mixed>>,
     *     kpis: array<string, mixed>,
     * }
     */
    public function buildPage(IndexMyEmployeeOvertimesRequest $request, ?int $employeeId): array
    {
        $validated = $request->validated();
        $page = (int) $validated['page'];
        $perPage = (int) $validated['per_page'];
        $organization = $this->branchContextService->defaultOrganization();
        $today = now()->toDateString();

        $emptyPaginator = new LengthAwarePaginator([], 0, $perPage, $page, [
            'path' => $request->url(),
            'pageName' => 'page',
        ]);

        if ($organization === null || $employeeId === null) {
            return [
                'paginator' => $emptyPaginator,
                'kpis' => $this->emptyOvertimeKpis($today),
            ];
        }

        $orgId = (int) $organization->id;

        $filtered = $this->baseMyOvertimeQuery($orgId, $employeeId);
        $this->applyMyOvertimeFilters($filtered, $validated);

        $kpis = $this->buildOvertimeKpis(clone $filtered, $today);

        $paginator = $filtered
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'overtimePolicy:id,code,name,organization_id,context,rate_multiplier',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->getCollection()->transform(static fn (EmployeeOvertime $row): array => TeamHrOvertimeRecordPresenter::toPageRow($row));

        return [
            'paginator' => $paginator,
            'kpis' => $kpis,
        ];
    }

    /**
     * @return Builder<EmployeeOvertime>
     */
    private function baseMyOvertimeQuery(int $organizationId, int $employeeId): Builder
    {
        return EmployeeOvertime::query()
            ->where('employee_overtimes.organization_id', $organizationId)
            ->where('employee_overtimes.employee_id', $employeeId)
            ->whereNull('employee_overtimes.deleted_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyMyOvertimeFilters(Builder $query, array $filters): void
    {
        $query->whereDate('employee_overtimes.ot_date', '>=', $filters['date_from'])
            ->whereDate('employee_overtimes.ot_date', '<=', $filters['date_to']);

        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $enum = $status === 'approved'
                ? EmployeeHrRecordStatus::Approved
                : EmployeeHrRecordStatus::Rejected;
            $query->where('employee_overtimes.status', $enum);
        }

        if (
            isset($filters['policy_code'])
            && is_string($filters['policy_code'])
            && $filters['policy_code'] !== ''
        ) {
            $code = (string) $filters['policy_code'];
            $query->whereHas('overtimePolicy', static fn (Builder $q): Builder => $q->where('overtime_policies.code', $code));
        }

        $this->applyMyOvertimeSearch($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyMyOvertimeSearch(Builder $query, array $filters): void
    {
        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        if ($q === '') {
            return;
        }

        $like = '%'.$q.'%';
        $query->where(function (Builder $w) use ($like): void {
            $w->whereHas('overtimePolicy', function (Builder $p) use ($like): void {
                $p->where('overtime_policies.code', 'like', $like)
                    ->orWhere('overtime_policies.name', 'like', $like);
            })->orWhere('employee_overtimes.reason', 'like', $like);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOvertimeKpis(string $today): array
    {
        return [
            'today' => $today,
            'approved_hours' => 0.0,
            'upcoming_count' => 0,
            'rejected_count' => 0,
            'entries_count' => 0,
            'approved_hours_by_policy' => [],
            'entries_rows' => [],
            'upcoming_rows' => [],
            'rejected_rows' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOvertimeKpis(Builder $filteredQuery, string $today): array
    {
        $entriesCount = (clone $filteredQuery)->count();

        $rejectedCount = (clone $filteredQuery)
            ->where('employee_overtimes.status', EmployeeHrRecordStatus::Rejected)
            ->count();

        $upcomingCount = (clone $filteredQuery)
            ->whereDate('employee_overtimes.ot_date', '>=', $today)
            ->count();

        $approvedRows = (clone $filteredQuery)
            ->where('employee_overtimes.status', EmployeeHrRecordStatus::Approved)
            ->with('overtimePolicy:id,code,name')
            ->orderBy('employee_overtimes.ot_date')
            ->orderBy('employee_overtimes.id')
            ->get();

        $approvedHours = 0.0;

        /** @var array<string, array{name: string, hours: float, record_count: int}> $byPolicy */
        $byPolicy = [];

        foreach ($approvedRows as $row) {
            $policy = $row->overtimePolicy;
            $code = $policy !== null ? (string) $policy->code : '';
            $name = $policy !== null ? (string) $policy->name : $code;
            $hours = (float) $row->hours;

            $approvedHours += $hours;

            if ($code === '') {
                continue;
            }

            if (! isset($byPolicy[$code])) {
                $byPolicy[$code] = ['name' => $name, 'hours' => 0.0, 'record_count' => 0];
            }

            $byPolicy[$code]['hours'] += $hours;
            $byPolicy[$code]['record_count']++;
        }

        $approvedHoursByPolicy = collect($byPolicy)
            ->map(static fn (array $row, string $code): array => [
                'code' => $code,
                'name' => $row['name'],
                'hours' => $row['hours'],
                'record_count' => $row['record_count'],
            ])
            ->sortByDesc('hours')
            ->values()
            ->all();

        $entriesRows = (clone $filteredQuery)
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'overtimePolicy:id,code,name,organization_id,context,rate_multiplier',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
            ])
            ->orderBy('employee_overtimes.ot_date')
            ->orderByDesc('employee_overtimes.submitted_at')
            ->orderByDesc('employee_overtimes.id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        $upcomingRows = (clone $filteredQuery)
            ->whereDate('employee_overtimes.ot_date', '>=', $today)
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'overtimePolicy:id,code,name,organization_id,context,rate_multiplier',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
            ])
            ->orderBy('employee_overtimes.ot_date')
            ->orderBy('employee_overtimes.id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        $rejectedRows = (clone $filteredQuery)
            ->where('employee_overtimes.status', EmployeeHrRecordStatus::Rejected)
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'overtimePolicy:id,code,name,organization_id,context,rate_multiplier',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        return [
            'today' => $today,
            'approved_hours' => $approvedHours,
            'upcoming_count' => $upcomingCount,
            'rejected_count' => $rejectedCount,
            'entries_count' => $entriesCount,
            'approved_hours_by_policy' => $approvedHoursByPolicy,
            'entries_rows' => $entriesRows->map(static fn (EmployeeOvertime $r): array => TeamHrOvertimeRecordPresenter::toPageRow($r))->all(),
            'upcoming_rows' => $upcomingRows->map(static fn (EmployeeOvertime $r): array => TeamHrOvertimeRecordPresenter::toPageRow($r))->all(),
            'rejected_rows' => $rejectedRows->map(static fn (EmployeeOvertime $r): array => TeamHrOvertimeRecordPresenter::toPageRow($r))->all(),
        ];
    }

    /**
     * @return list<array{code: string, name: string, context: string, rateMultiplier: float}>
     */
    public function overtimePolicyOptions(?Organization $organization): array
    {
        if ($organization === null) {
            return [];
        }

        return OvertimePolicy::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['code', 'name', 'context', 'rate_multiplier'])
            ->map(static fn (OvertimePolicy $p): array => [
                'code' => (string) $p->code,
                'name' => (string) $p->name,
                'context' => $p->context->value,
                'rateMultiplier' => (float) $p->rate_multiplier,
            ])
            ->values()
            ->all();
    }
}
