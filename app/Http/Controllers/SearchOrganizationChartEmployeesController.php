<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchOrgChartEmployeesRequest;
use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\BranchContextService;
use App\Services\OrganizationChartActionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SearchOrganizationChartEmployeesController extends Controller
{
    public function __invoke(
        SearchOrgChartEmployeesRequest $request,
        BranchContextService $branchContextService,
        OrganizationChartActionAccessService $actionAccessService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();
        if ($organization === null) {
            abort(404);
        }

        $user = $request->user();
        $chartRoot = $branchContextService->findSelectableBranchRoot($request->chartBranchId(), $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            abort(404);
        }

        abort_unless($actionAccessService->canManageChartBranch($user, $chartRoot), 403);

        $node = $actionAccessService->findNodeWithinRootById($chartRoot, $request->parsedNodeUnitId() ?? 0);
        if ($request->parsedNodeUnitId() !== null && ! $node instanceof OrganizationalUnit) {
            abort(404);
        }

        $today = now()->toDateString();
        $queryText = $request->queryText();
        $employees = Employee::query()
            ->select([
                'employees.id',
                'employees.id_number',
                'employees.first_name',
                'employees.middle_name',
                'employees.last_name',
                'employees.suffix',
            ])
            ->whereNull('employees.deleted_at')
            ->whereHas('employments', function ($query) use ($today): void {
                $query->whereNull('deleted_at')
                    ->where('is_current', true)
                    ->where('employment_status', EmployeeEmployment::STATUS_ACTIVE)
                    ->where(function ($dateScope) use ($today): void {
                        $dateScope->whereNull('separation_date')
                            ->orWhereDate('separation_date', '>=', $today);
                    });
            })
            ->where(function ($affiliationScope) use ($organization, $chartRoot, $today): void {
                $affiliationScope->whereHas('affiliations', function ($query) use ($organization, $chartRoot, $today): void {
                    $query->where('organization_id', $organization->id)
                        ->where('root_unit_id', $chartRoot->id)
                        ->whereNull('deleted_at')
                        ->where(function ($scoped) use ($today): void {
                            $scoped->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
                });

                // Org-wide affiliation (same organization, no branch root): anyone who may manage this chart can assign them here.
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
            ->when($node instanceof OrganizationalUnit, function ($query) use ($node, $today): void {
                $query->whereDoesntHave('assignments', function ($assignmentQuery) use ($node, $today): void {
                    $assignmentQuery->where('organizational_unit_id', $node->id)
                        ->whereNull('deleted_at')
                        ->where(function ($dateScope) use ($today): void {
                            $dateScope->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
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

                return [
                    'id' => (int) $employee->id,
                    'employee_id' => (int) $employee->id,
                    'employee_number' => $employee->id_number,
                    'full_name' => $this->formatEmployeeDisplayName($employee),
                    'active_position_title' => $this->resolveActivePositionTitle($employee),
                    'avatar_url' => $this->resolveAvatarUrl($employee),
                    'positions' => $positions,
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
