<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchTeamHrFormEmployeesRequest;
use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SearchTeamHrFormEmployeesController extends Controller
{
    public function __invoke(
        SearchTeamHrFormEmployeesRequest $request,
        BranchContextService $branchContextService,
        ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();
        if ($organization === null) {
            abort(404);
        }

        $workspace = $branchContextService->workspaceBranchContext($request);
        if ($workspace === null || (int) $workspace['id'] !== $request->chartBranchId()) {
            abort(403);
        }

        $branchRootId = (int) $workspace['id'];
        $allowedIds = $scheduleAssignmentAccessService->teamHrFormSelectableUnitIds((int) $organization->id, $branchRootId);

        $unitId = $request->unitId();
        if (! in_array($unitId, $allowedIds, true)) {
            abort(404);
        }

        $queryText = $request->queryText();

        $today = now()->toDateString();
        $canIncludeOrgWide = $request->user()?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;

        $employees = Employee::query()
            ->select([
                'employees.id',
                'employees.id_number',
                'employees.attendance_id',
                'employees.work_schedule_template_id',
                'employees.first_name',
                'employees.middle_name',
                'employees.last_name',
                'employees.suffix',
            ])
            ->whereNull('employees.deleted_at')
            ->whereHas('employments', function ($query): void {
                $query->whereNull('deleted_at')
                    ->where('is_current', true)
                    ->where('employment_status', EmployeeEmployment::STATUS_ACTIVE);
            })
            ->where(function ($affiliationScope) use ($organization, $branchRootId, $today, $canIncludeOrgWide): void {
                $affiliationScope->whereHas('affiliations', function ($query) use ($organization, $branchRootId, $today): void {
                    $query->where('organization_id', $organization->id)
                        ->where('root_unit_id', $branchRootId)
                        ->whereNull('deleted_at')
                        ->where(function ($scoped) use ($today): void {
                            $scoped->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
                });

                if (! $canIncludeOrgWide) {
                    return;
                }

                $affiliationScope->orWhereHas('affiliations', function ($query) use ($organization, $today): void {
                    $query->where('organization_id', $organization->id)
                        ->whereNull('root_unit_id')
                        ->whereNull('deleted_at')
                        ->where(function ($scoped) use ($today): void {
                            $scoped->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
                });
            })
            ->whereHas('assignments', function ($query) use ($unitId, $today): void {
                $query->where('organizational_unit_id', $unitId)
                    ->whereNull('deleted_at')
                    ->where(function ($dateScope) use ($today): void {
                        $dateScope->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    });
            })
            ->when($queryText !== '', function ($query) use ($queryText): void {
                $like = '%'.$queryText.'%';
                $query->where(function ($search) use ($like): void {
                    $search->where('employees.first_name', 'like', $like)
                        ->orWhere('employees.middle_name', 'like', $like)
                        ->orWhere('employees.last_name', 'like', $like)
                        ->orWhere('employees.id_number', 'like', $like);
                });
            })
            ->with([
                'workScheduleTemplate',
                'user:id,employee_id,avatar_path',
                'positions' => function ($query) use ($today): void {
                    $query->whereNull('deleted_at')
                        ->where(function ($dateScope) use ($today): void {
                            $dateScope->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        })
                        ->with('position:id,title')
                        ->orderByDesc('is_primary')
                        ->orderByDesc('start_date')
                        ->orderByDesc('id');
                },
            ])
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->limit($request->limit())
            ->get();

        return response()->json([
            'data' => $employees->map(function (Employee $employee): array {
                $positions = $this->resolvePositionOptions($employee);

                $template = $employee->workScheduleTemplate;

                return [
                    'id' => (int) $employee->id,
                    'employee_id' => (int) $employee->id,
                    'employee_number' => $employee->id_number,
                    'full_name' => $this->formatEmployeeDisplayName($employee),
                    'active_position_title' => $this->resolveActivePositionTitle($employee),
                    'avatar_url' => $this->resolveAvatarUrl($employee),
                    'positions' => $positions,
                    'attendance_id' => is_string($employee->attendance_id) && $employee->attendance_id !== ''
                        ? $employee->attendance_id
                        : null,
                    'work_schedule_template_id' => $employee->work_schedule_template_id !== null
                        ? (int) $employee->work_schedule_template_id
                        : null,
                    'work_schedule_template' => $template !== null
                        ? $template->toShiftRuleArray()
                        : null,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function resolvePositionOptions(Employee $employee): array
    {
        /** @var Collection<int, EmployeePosition> $positions */
        $positions = $employee->positions;

        return $positions
            ->map(function ($employeePosition): ?array {
                $position = $employeePosition->position;
                $title = is_string($position?->title) ? trim((string) $position->title) : '';
                if ($position === null || $title === '') {
                    return null;
                }

                return [
                    'id' => (int) $position->id,
                    'title' => $title,
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null)
            ->unique('id')
            ->values()
            ->all();
    }

    private function resolveAvatarUrl(Employee $employee): ?string
    {
        $user = $employee->user;
        if (! $user instanceof User) {
            return null;
        }

        $avatarPath = $user->avatar_path;
        if (! is_string($avatarPath) || $avatarPath === '') {
            return null;
        }

        return asset('storage/'.ltrim($avatarPath, '/'));
    }

    private function resolveActivePositionTitle(Employee $employee): ?string
    {
        /** @var Collection<int, EmployeePosition> $positions */
        $positions = $employee->positions;
        $title = $positions->first()?->position?->title;

        return is_string($title) && $title !== '' ? $title : null;
    }

    private function formatEmployeeDisplayName(Employee $employee): string
    {
        $parts = array_values(array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->suffix,
        ], fn (?string $part): bool => filled($part)));

        if ($parts === []) {
            return 'Employee #'.$employee->id;
        }

        return implode(' ', $parts);
    }
}
