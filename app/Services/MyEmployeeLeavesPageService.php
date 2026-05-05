<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Http\Requests\IndexMyEmployeeLeavesRequest;
use App\Models\EmployeeLeave;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Support\TeamHrEmployeeDirectoryExtras;
use App\Support\TeamHrLeaveRecordPresenter;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class MyEmployeeLeavesPageService
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
    public function buildPage(IndexMyEmployeeLeavesRequest $request, ?int $employeeId): array
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
                'kpis' => $this->emptyLeaveKpis($today),
            ];
        }

        $orgId = (int) $organization->id;

        $filtered = $this->baseMyLeaveQuery($orgId, $employeeId);
        $this->applyMyLeaveFilters($filtered, $validated);

        $kpis = $this->buildLeaveKpis(clone $filtered, $today);

        $paginator = $filtered
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'leavePolicy:id,code,name,organization_id',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
                'leaveDays',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->getCollection()->transform(static fn (EmployeeLeave $leave): array => TeamHrLeaveRecordPresenter::toPageRow($leave));

        return [
            'paginator' => $paginator,
            'kpis' => $kpis,
        ];
    }

    /**
     * @return Builder<EmployeeLeave>
     */
    private function baseMyLeaveQuery(int $organizationId, int $employeeId): Builder
    {
        return EmployeeLeave::query()
            ->where('employee_leaves.organization_id', $organizationId)
            ->where('employee_leaves.employee_id', $employeeId)
            ->whereNull('employee_leaves.deleted_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyMyLeaveFilters(Builder $query, array $filters): void
    {
        $query->whereDate('employee_leaves.start_date', '<=', $filters['date_to'])
            ->whereDate('employee_leaves.end_date', '>=', $filters['date_from']);

        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $query->where(
                'employee_leaves.status',
                EmployeeHrRecordStatus::from($status === 'approved' ? 'approved' : 'rejected'),
            );
        }

        if (
            isset($filters['leave_type'])
            && is_string($filters['leave_type'])
            && $filters['leave_type'] !== ''
        ) {
            $code = (string) $filters['leave_type'];
            $query->whereHas('leavePolicy', static fn (Builder $q): Builder => $q->where('leave_policies.code', $code));
        }

        $this->applyMyLeaveSearch($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyMyLeaveSearch(Builder $query, array $filters): void
    {
        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        if ($q === '') {
            return;
        }

        $like = '%'.$q.'%';
        $query->where(function (Builder $w) use ($like): void {
            $w->whereHas('leavePolicy', function (Builder $p) use ($like): void {
                $p->where('leave_policies.code', 'like', $like)
                    ->orWhere('leave_policies.name', 'like', $like);
            })->orWhere('employee_leaves.reason', 'like', $like);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLeaveKpis(string $today): array
    {
        return [
            'today' => $today,
            'approved_calendar_days' => 0,
            'upcoming_count' => 0,
            'vacation_calendar_days' => 0,
            'rejected_count' => 0,
            'approved_usage_by_type' => [],
            'vacation_rows' => [],
            'upcoming_rows' => [],
            'rejected_rows' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLeaveKpis(Builder $filteredQuery, string $today): array
    {
        $rejectedCount = (clone $filteredQuery)
            ->where('employee_leaves.status', EmployeeHrRecordStatus::Rejected)
            ->count();

        $upcomingCount = (clone $filteredQuery)
            ->whereDate('employee_leaves.start_date', '>=', $today)
            ->count();

        $rejectedRows = (clone $filteredQuery)
            ->where('employee_leaves.status', EmployeeHrRecordStatus::Rejected)
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'leavePolicy:id,code,name,organization_id',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
                'leaveDays',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        $approvedLeaves = (clone $filteredQuery)
            ->where('employee_leaves.status', EmployeeHrRecordStatus::Approved)
            ->with('leavePolicy:id,code,name')
            ->orderBy('employee_leaves.start_date')
            ->orderBy('employee_leaves.id')
            ->get(['employee_leaves.id', 'employee_leaves.start_date', 'employee_leaves.end_date', 'employee_leaves.leave_policy_id']);

        $approvedCalendarDays = 0;
        $vacationCalendarDays = 0;

        /** @var array<string, array{name: string, days: float|int, record_count: int}> $byType */
        $byType = [];

        foreach ($approvedLeaves as $leave) {
            $policy = $leave->leavePolicy;
            if ($policy === null) {
                continue;
            }

            $code = (string) $policy->code;
            $name = (string) $policy->name;
            $days = $this->inclusiveCalendarDays(
                $leave->start_date->format('Y-m-d'),
                $leave->end_date->format('Y-m-d'),
            );

            $approvedCalendarDays += $days;

            if (! isset($byType[$code])) {
                $byType[$code] = ['name' => $name, 'days' => 0, 'record_count' => 0];
            }

            $byType[$code]['days'] += $days;
            $byType[$code]['record_count']++;

            if ($code === 'VL') {
                $vacationCalendarDays += $days;
            }
        }

        $approvedUsageByType = collect($byType)
            ->map(static fn (array $row, string $code): array => [
                'code' => $code,
                'name' => $row['name'],
                'days' => $row['days'],
                'record_count' => $row['record_count'],
            ])
            ->sortByDesc('days')
            ->values()
            ->all();

        $vacationLeaves = (clone $filteredQuery)
            ->where('employee_leaves.status', EmployeeHrRecordStatus::Approved)
            ->whereHas('leavePolicy', static fn (Builder $q): Builder => $q->where('leave_policies.code', 'VL'))
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'leavePolicy:id,code,name,organization_id',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
                'leaveDays',
            ])
            ->orderBy('employee_leaves.start_date')
            ->orderBy('employee_leaves.id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        $upcomingRows = (clone $filteredQuery)
            ->whereDate('employee_leaves.start_date', '>=', $today)
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'leavePolicy:id,code,name,organization_id',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
                'leaveDays',
            ])
            ->orderBy('employee_leaves.start_date')
            ->orderBy('employee_leaves.id')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        return [
            'today' => $today,
            'approved_calendar_days' => $approvedCalendarDays,
            'upcoming_count' => $upcomingCount,
            'vacation_calendar_days' => $vacationCalendarDays,
            'rejected_count' => $rejectedCount,
            'approved_usage_by_type' => $approvedUsageByType,
            'vacation_rows' => $vacationLeaves->map(static fn (EmployeeLeave $l): array => TeamHrLeaveRecordPresenter::toPageRow($l))->all(),
            'upcoming_rows' => $upcomingRows->map(static fn (EmployeeLeave $l): array => TeamHrLeaveRecordPresenter::toPageRow($l))->all(),
            'rejected_rows' => $rejectedRows->map(static fn (EmployeeLeave $l): array => TeamHrLeaveRecordPresenter::toPageRow($l))->all(),
        ];
    }

    private function inclusiveCalendarDays(string $start, string $end): int
    {
        $a = new DateTimeImmutable($start);
        $b = new DateTimeImmutable($end);

        return (int) $a->diff($b)->days + 1;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function leavePolicyOptions(?Organization $organization): array
    {
        if ($organization === null) {
            return [];
        }

        return LeavePolicy::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(static fn (LeavePolicy $p): array => [
                'code' => (string) $p->code,
                'name' => (string) $p->name,
            ])
            ->values()
            ->all();
    }
}
