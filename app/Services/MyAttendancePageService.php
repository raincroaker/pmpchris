<?php

namespace App\Services;

use App\Enums\AttendanceRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Http\Requests\IndexMyAttendanceRequest;
use App\Models\EmployeeAttendanceDay;
use App\Support\TeamAttendanceRecordPresenter;
use App\Support\TeamHrEmployeeDirectoryExtras;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class MyAttendancePageService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function buildPage(IndexMyAttendanceRequest $request, ?int $employeeId): LengthAwarePaginator
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
            return $emptyPaginator;
        }

        $filtered = $this->baseMyAttendanceQuery((int) $organization->id, $employeeId);
        $this->applyMyAttendanceFilters($filtered, $validated);

        $paginator = $filtered
            ->with([
                'employee' => TeamHrEmployeeDirectoryExtras::eagerLoadEmployeeForAttendancePresenters($today),
                'employee.user:id,employee_id,avatar_path',
                'organizationalUnit:id,code,name,organization_id,unit_type_id',
                'organizationalUnit.unitType:id,name,color',
                'segments',
                'workScheduleTemplate' => static fn ($q) => $q->withTrashed(),
                'createdByUser' => static fn ($q) => $q->with('employee'),
                'updatedByUser' => static fn ($q) => $q->with('employee'),
            ])
            ->orderBy(
                'employee_attendance_days.work_date',
                ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            )
            ->orderByDesc('employee_attendance_days.id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $paginator->getCollection()->transform(static fn (EmployeeAttendanceDay $day): array => TeamAttendanceRecordPresenter::toPageRow($day));

        return $paginator;
    }

    /**
     * @return Builder<EmployeeAttendanceDay>
     */
    private function baseMyAttendanceQuery(int $organizationId, int $employeeId): Builder
    {
        $query = EmployeeAttendanceDay::query()
            ->where('employee_attendance_days.organization_id', $organizationId)
            ->where('employee_attendance_days.employee_id', $employeeId)
            ->where('employee_attendance_days.deleted_at', null);

        /** @var Builder<EmployeeAttendanceDay> $query */
        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyMyAttendanceFilters(Builder $query, array $filters): void
    {
        $query->whereDate('employee_attendance_days.work_date', '>=', $filters['date_from'])
            ->whereDate('employee_attendance_days.work_date', '<=', $filters['date_to']);

        $status = (string) ($filters['status'] ?? 'all');
        if ($status !== 'all') {
            $query->where(
                'employee_attendance_days.status',
                AttendanceRecordStatus::from($status),
            );
        }

        $recording = (string) ($filters['recording_style'] ?? 'simple');
        if ($recording === 'simple') {
            $query->where('employee_attendance_days.clock_pattern', WorkScheduleClockPattern::SinglePair)
                ->where('employee_attendance_days.is_overnight_schedule', false);
        } elseif ($recording === 'split') {
            $query->where('employee_attendance_days.clock_pattern', WorkScheduleClockPattern::SplitSessions)
                ->where('employee_attendance_days.is_overnight_schedule', false);
        } elseif ($recording === 'overnight') {
            $query->where('employee_attendance_days.is_overnight_schedule', true);
        }

        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $w) use ($like): void {
                $w->where('employee_attendance_days.ingest_key', 'like', $like)
                    ->orWhereHas('employee', function (Builder $e) use ($like): void {
                        $e->where('employees.attendance_id', 'like', $like);
                    });
            });
        }
    }
}
