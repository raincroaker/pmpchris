<?php

namespace App\Services;

use App\Enums\EmployeeHrRecordStatus;
use App\Http\Requests\IndexTeamHrEmployeeOvertimesRequest;
use App\Models\EmployeeOvertime;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use App\Support\EmployeeBranchDirectoryFilter;
use App\Support\TeamHrEmployeeDirectoryExtras;
use App\Support\TeamHrOvertimeRecordPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class TeamHrEmployeeOvertimesPageService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
    ) {}

    /**
     * @return array{
     *     paginator: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function buildPage(IndexTeamHrEmployeeOvertimesRequest $request): array
    {
        $validated = $request->validated();
        $organization = $this->branchContextService->defaultOrganization();
        $workspace = $this->branchContextService->workspaceBranchContext($request);

        $page = (int) $validated['page'];
        $perPage = (int) $validated['per_page'];

        if ($organization === null || $workspace === null) {
            $filters = $request->inertiaTeamOvertimeFilters();
            $filters['unit_id'] = null;

            return [
                'paginator' => new LengthAwarePaginator([], 0, $perPage, $page, [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]),
                'filters' => $filters,
            ];
        }

        $orgId = (int) $organization->id;
        $branchRootId = (int) $workspace['id'];
        $today = now()->toDateString();
        $selectableUnitIds = $this->scheduleAssignmentAccessService->teamHrFormSelectableUnitIds($orgId, $branchRootId);

        if (
            isset($validated['unit_id'])
            && $validated['unit_id'] !== null
            && ! in_array((int) $validated['unit_id'], $selectableUnitIds, true)
        ) {
            $validated['unit_id'] = null;
        }

        $paginatedQuery = $this->baseEmployeeOvertimeQuery($orgId, $branchRootId, $today);
        $this->applyOvertimeFilters($paginatedQuery, $validated);

        $sort = (string) ($validated['sort'] ?? 'ot_date');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        $paginator = $paginatedQuery
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForPresenters($today),
                'overtimePolicy:id,code,name,organization_id,context,rate_multiplier',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'approver:id,first_name,middle_name,last_name,suffix,id_number',
            ])
            ->when(
                $sort === 'status',
                static fn (Builder $q): Builder => $q
                    ->orderBy('employee_overtimes.status', $sortDirection)
                    ->orderBy('employee_overtimes.ot_date', 'desc')
                    ->orderByDesc('employee_overtimes.id'),
                static fn (Builder $q): Builder => $q
                    ->when(
                        $sort === 'approve_date',
                        static fn (Builder $q2): Builder => $q2
                            ->orderBy('employee_overtimes.decided_at', $sortDirection)
                            ->orderBy('employee_overtimes.ot_date', 'desc')
                            ->orderByDesc('employee_overtimes.id'),
                        static fn (Builder $q2): Builder => $q2
                            ->orderBy('employee_overtimes.ot_date', $sortDirection)
                            ->orderByDesc('employee_overtimes.id'),
                    ),
            )
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->getCollection()->transform(static fn (EmployeeOvertime $row): array => TeamHrOvertimeRecordPresenter::toPageRow($row));

        return [
            'paginator' => $paginator,
            'filters' => [
                ...$request->inertiaTeamOvertimeFilters(),
                'unit_id' => $validated['unit_id'] ?? null,
            ],
        ];
    }

    /**
     * @return Builder<EmployeeOvertime>
     */
    private function baseEmployeeOvertimeQuery(int $organizationId, int $branchRootId, string $today): Builder
    {
        return EmployeeOvertime::query()
            ->where('employee_overtimes.organization_id', $organizationId)
            ->whereNull('employee_overtimes.deleted_at')
            ->whereHas('employee', function ($query) use ($organizationId, $branchRootId, $today): void {
                $query->whereNull('employees.deleted_at');
                EmployeeBranchDirectoryFilter::apply($query, $organizationId, $branchRootId, $today);
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyOvertimeFilters(Builder $query, array $filters): void
    {
        $query->whereDate('employee_overtimes.ot_date', '>=', $filters['date_from'])
            ->whereDate('employee_overtimes.ot_date', '<=', $filters['date_to']);

        if (! empty($filters['unit_id'])) {
            $query->where('employee_overtimes.organizational_unit_id', (int) $filters['unit_id']);
        }

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

        if (
            ! empty($filters['approve_from'])
            && ! empty($filters['approve_to'])
        ) {
            $query->whereNotNull('employee_overtimes.decided_at')
                ->whereDate('employee_overtimes.decided_at', '>=', $filters['approve_from'])
                ->whereDate('employee_overtimes.decided_at', '<=', $filters['approve_to']);
        }

        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $w) use ($like): void {
                $w->whereHas('employee', function (Builder $e) use ($like): void {
                    $e->where('employees.id_number', 'like', $like)
                        ->orWhere('employees.first_name', 'like', $like)
                        ->orWhere('employees.last_name', 'like', $like)
                        ->orWhere('employees.middle_name', 'like', $like);
                })->orWhereHas('overtimePolicy', function (Builder $p) use ($like): void {
                    $p->where('overtime_policies.code', 'like', $like)
                        ->orWhere('overtime_policies.name', 'like', $like);
                })->orWhere('employee_overtimes.reason', 'like', $like);
            });
        }
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
