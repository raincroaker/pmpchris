<?php

namespace App\Services;

use App\Models\AssignmentPosition;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationChartEmployeeAssignmentMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private OrganizationChartActionAccessService $organizationChartActionAccessService,
    ) {}

    /**
     * @return array{assignment_id: int, employee_id: int, position_title: string|null, is_head: bool}
     */
    public function createAssignment(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        int $employeeId,
        int $positionId,
        bool $isHead,
        bool $isPrimary,
        string $startDate,
    ): array {
        [$organization, $chartRoot, $nodeUnit] = $this->resolveAuthorizedUnitContext($user, $chartBranchId, $nodeId);

        $employee = $this->resolveEligibleEmployeeForUnit($user, $organization, $chartRoot, $nodeUnit, $employeeId);
        if (! $employee instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'Selected employee is not available for this unit.',
            ]);
        }

        $employment = $this->resolveCurrentActiveEmployment($employeeId);
        if (! $employment instanceof EmployeeEmployment) {
            throw ValidationException::withMessages([
                'employee_id' => 'Selected employee does not have an active employment record.',
            ]);
        }

        $employeePosition = $this->resolveActiveEmployeePositionForEmployee(
            $employeeId,
            $positionId,
            (int) $employment->id,
        );
        if (! $employeePosition instanceof EmployeePosition) {
            throw ValidationException::withMessages([
                'position_id' => 'Selected position is not available for this employee.',
            ]);
        }

        $effectiveStartDate = Carbon::parse($startDate)->startOfDay();
        $effectiveStartDateString = $effectiveStartDate->toDateString();

        /** @var EmployeeAssignment $assignment */
        $assignment = DB::transaction(function () use ($employeeId, $employment, $nodeUnit, $isHead, $isPrimary, $employeePosition, $effectiveStartDate, $effectiveStartDateString): EmployeeAssignment {
            $existingActiveAssignment = EmployeeAssignment::query()
                ->where('employee_id', $employeeId)
                ->where('organizational_unit_id', $nodeUnit->id)
                ->whereNull('deleted_at')
                ->where(function ($dateScope) use ($effectiveStartDateString): void {
                    $dateScope->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $effectiveStartDateString);
                })
                ->first();
            if ($existingActiveAssignment instanceof EmployeeAssignment) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Selected employee is already assigned to this unit.',
                ]);
            }

            $assignment = EmployeeAssignment::query()->create([
                'employee_id' => $employeeId,
                'employee_employment_id' => $employment->id,
                'organization_id' => null,
                'organizational_unit_id' => $nodeUnit->id,
                'is_primary' => $isPrimary,
                'is_head' => $isHead,
                'start_date' => $effectiveStartDate,
                'end_date' => null,
            ]);

            AssignmentPosition::query()->create([
                'employee_assignment_id' => $assignment->id,
                'employee_position_id' => $employeePosition->id,
                'is_primary_for_assignment' => true,
                'start_date' => $effectiveStartDate,
                'end_date' => $effectiveStartDate->copy()->addYears(20),
            ]);

            return $assignment;
        });

        $fresh = EmployeeAssignment::query()
            ->whereKey($assignment->id)
            ->with(['assignmentPositions.employeePosition.position'])
            ->first();
        if (! $fresh instanceof EmployeeAssignment) {
            throw (new ModelNotFoundException)->setModel(EmployeeAssignment::class);
        }

        return [
            'assignment_id' => (int) $fresh->id,
            'employee_id' => (int) $fresh->employee_id,
            'position_title' => $this->resolveAssignmentLinkedPositionTitle($fresh),
            'is_head' => (bool) $fresh->is_head,
        ];
    }

    /**
     * @return array{assignment_id: int, employee_id: int, position_title: string|null, is_head: bool}
     */
    public function updateAssignment(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        EmployeeAssignment $assignment,
        int $positionId,
        bool $isHead,
        bool $isPrimary,
        string $effectiveDate,
    ): array {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $chartRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
        }

        if (! $this->organizationChartActionAccessService->canManageChartBranch($user, $chartRoot)) {
            throw new AuthorizationException('You are not allowed to manage employees in this branch.');
        }

        $nodeUnit = $this->resolveUnitNodeWithinRoot($chartRoot, $nodeId);
        if (! $nodeUnit instanceof OrganizationalUnit) {
            throw ValidationException::withMessages([
                'node_id' => 'Invalid target node.',
            ]);
        }

        if (! $this->organizationChartActionAccessService->canManageNode($user, $chartRoot, $nodeUnit)) {
            throw new AuthorizationException('You are not allowed to manage employees in this unit.');
        }

        if ($assignment->organizational_unit_id === null) {
            throw ValidationException::withMessages([
                'node_id' => 'Only unit assignments can be edited here.',
            ]);
        }

        if ((int) $assignment->organizational_unit_id !== (int) $nodeUnit->id) {
            throw ValidationException::withMessages([
                'node_id' => 'Assignment does not belong to the selected unit.',
            ]);
        }

        if ($assignment->end_date !== null) {
            throw ValidationException::withMessages([
                'node_id' => 'This assignment is no longer active.',
            ]);
        }

        $employeePosition = $this->resolveActiveEmployeePositionForEmployee(
            (int) $assignment->employee_id,
            $positionId,
            (int) $assignment->employee_employment_id,
        );
        if (! $employeePosition instanceof EmployeePosition) {
            throw ValidationException::withMessages([
                'position_id' => 'Selected position is not available for this employee.',
            ]);
        }

        $effectiveDateValue = Carbon::parse($effectiveDate)->startOfDay();
        $effectiveDateString = $effectiveDateValue->toDateString();
        $effectiveDateMinusOneDay = $effectiveDateValue->copy()->subDay();

        $assignmentStartDate = $assignment->start_date instanceof Carbon
            ? $assignment->start_date->copy()->startOfDay()
            : Carbon::parse((string) $assignment->start_date)->startOfDay();
        if ($effectiveDateValue->lt($assignmentStartDate)) {
            throw ValidationException::withMessages([
                'effective_date' => 'Effective date must be on or after the assignment start date.',
            ]);
        }

        $assignment->is_head = $isHead;
        $assignment->is_primary = $isPrimary;
        $assignment->save();

        $assignment->loadMissing(['assignmentPositions.employeePosition.position']);

        /** @var Collection<int, AssignmentPosition> $links */
        $links = $assignment->assignmentPositions;
        $activeLinks = $links->filter(function (AssignmentPosition $ap) use ($effectiveDateString): bool {
            if ($ap->deleted_at !== null) {
                return false;
            }
            if ($ap->end_date === null) {
                return true;
            }

            return $ap->end_date->toDateString() >= $effectiveDateString;
        });

        $existingForSelection = $activeLinks->firstWhere('employee_position_id', $employeePosition->id);

        foreach ($activeLinks as $ap) {
            if ((int) $ap->employee_position_id === (int) $employeePosition->id) {
                continue;
            }

            $ap->is_primary_for_assignment = false;
            $ap->end_date = $effectiveDateMinusOneDay;
            $ap->save();
        }

        if ($existingForSelection instanceof AssignmentPosition) {
            $existingForSelection->is_primary_for_assignment = true;
            $existingForSelection->start_date = $effectiveDateValue;
            $existingForSelection->end_date = $effectiveDateValue->copy()->addYears(20);
            $existingForSelection->save();
        } else {
            AssignmentPosition::query()->create([
                'employee_assignment_id' => $assignment->id,
                'employee_position_id' => $employeePosition->id,
                'is_primary_for_assignment' => true,
                'start_date' => $effectiveDateValue,
                'end_date' => $effectiveDateValue->copy()->addYears(20),
            ]);
        }

        $fresh = EmployeeAssignment::query()
            ->whereKey($assignment->id)
            ->with(['assignmentPositions.employeePosition.position'])
            ->first();
        if (! $fresh instanceof EmployeeAssignment) {
            throw (new ModelNotFoundException)->setModel(EmployeeAssignment::class);
        }

        return [
            'assignment_id' => (int) $fresh->id,
            'employee_id' => (int) $fresh->employee_id,
            'position_title' => $this->resolveAssignmentLinkedPositionTitle($fresh),
            'is_head' => (bool) $fresh->is_head,
        ];
    }

    public function removeAssignment(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        EmployeeAssignment $assignment,
        string $endDate,
    ): void {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $chartRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
        }

        if (! $this->organizationChartActionAccessService->canManageChartBranch($user, $chartRoot)) {
            throw new AuthorizationException('You are not allowed to manage employees in this branch.');
        }

        $nodeUnit = $this->resolveUnitNodeWithinRoot($chartRoot, $nodeId);
        if (! $nodeUnit instanceof OrganizationalUnit) {
            throw ValidationException::withMessages([
                'node_id' => 'Invalid target node.',
            ]);
        }

        if (! $this->organizationChartActionAccessService->canManageNode($user, $chartRoot, $nodeUnit)) {
            throw new AuthorizationException('You are not allowed to manage employees in this unit.');
        }

        if ($assignment->organizational_unit_id === null) {
            throw ValidationException::withMessages([
                'node_id' => 'Only unit assignments can be removed here.',
            ]);
        }

        if ((int) $assignment->organizational_unit_id !== (int) $nodeUnit->id) {
            throw ValidationException::withMessages([
                'node_id' => 'Assignment does not belong to the selected unit.',
            ]);
        }

        if ($assignment->end_date !== null) {
            return;
        }

        $effectiveEndDate = Carbon::parse($endDate)->startOfDay();
        $effectiveEndDateString = $effectiveEndDate->toDateString();
        $assignmentStartDate = $assignment->start_date instanceof Carbon
            ? $assignment->start_date->copy()->startOfDay()
            : Carbon::parse((string) $assignment->start_date)->startOfDay();

        if ($effectiveEndDate->lt($assignmentStartDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after the assignment start date.',
            ]);
        }

        $assignment->end_date = $effectiveEndDate;
        $assignment->save();
        $assignment->delete();

        $assignment->loadMissing(['assignmentPositions']);

        /** @var Collection<int, AssignmentPosition> $links */
        $links = $assignment->assignmentPositions;
        foreach ($links as $ap) {
            if ($ap->deleted_at !== null) {
                continue;
            }
            if ($ap->end_date !== null && $ap->end_date->toDateString() < $effectiveEndDateString) {
                continue;
            }

            $ap->is_primary_for_assignment = false;
            $ap->end_date = $effectiveEndDate;
            $ap->save();
        }
    }

    /**
     * @return array{0: Organization, 1: OrganizationalUnit, 2: OrganizationalUnit}
     */
    private function resolveAuthorizedUnitContext(?User $user, int $chartBranchId, string $nodeId): array
    {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $chartRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
        }

        if (! $this->organizationChartActionAccessService->canManageChartBranch($user, $chartRoot)) {
            throw new AuthorizationException('You are not allowed to manage employees in this branch.');
        }

        $nodeUnit = $this->resolveUnitNodeWithinRoot($chartRoot, $nodeId);
        if (! $nodeUnit instanceof OrganizationalUnit) {
            throw ValidationException::withMessages([
                'node_id' => 'Invalid target node.',
            ]);
        }

        if (! $this->organizationChartActionAccessService->canManageNode($user, $chartRoot, $nodeUnit)) {
            throw new AuthorizationException('You are not allowed to manage employees in this unit.');
        }

        return [$organization, $chartRoot, $nodeUnit];
    }

    private function resolveUnitNodeWithinRoot(OrganizationalUnit $chartRoot, string $nodeId): ?OrganizationalUnit
    {
        if (! str_starts_with($nodeId, 'unit-')) {
            return null;
        }

        $unitId = (int) substr($nodeId, strlen('unit-'));

        return $this->organizationChartActionAccessService->findNodeWithinRootById($chartRoot, $unitId);
    }

    private function resolveEligibleEmployeeForUnit(
        ?User $user,
        Organization $organization,
        OrganizationalUnit $chartRoot,
        OrganizationalUnit $nodeUnit,
        int $employeeId,
    ): ?Employee {
        if ($employeeId <= 0) {
            return null;
        }

        $today = now()->toDateString();
        $canIncludeOrgWide = $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;

        return Employee::query()
            ->whereKey($employeeId)
            ->whereNull('employees.deleted_at')
            ->whereHas('employments', function ($query): void {
                $query->whereNull('deleted_at')
                    ->where('is_current', true)
                    ->where('employment_status', EmployeeEmployment::STATUS_ACTIVE);
            })
            ->where(function ($affiliationScope) use ($organization, $chartRoot, $today, $canIncludeOrgWide): void {
                $affiliationScope->whereHas('affiliations', function ($query) use ($organization, $chartRoot, $today): void {
                    $query->where('organization_id', $organization->id)
                        ->where('root_unit_id', $chartRoot->id)
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
            ->whereDoesntHave('assignments', function ($assignmentQuery) use ($nodeUnit, $today): void {
                $assignmentQuery->where('organizational_unit_id', $nodeUnit->id)
                    ->whereNull('deleted_at')
                    ->where(function ($dateScope) use ($today): void {
                        $dateScope->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    });
            })
            ->first();
    }

    private function resolveCurrentActiveEmployment(int $employeeId): ?EmployeeEmployment
    {
        $today = now()->toDateString();

        return EmployeeEmployment::query()
            ->where('employee_id', $employeeId)
            ->whereNull('deleted_at')
            ->where('is_current', true)
            ->where('employment_status', EmployeeEmployment::STATUS_ACTIVE)
            ->where(function ($dateScope) use ($today): void {
                $dateScope->whereNull('separation_date')
                    ->orWhereDate('separation_date', '>=', $today);
            })
            ->first();
    }

    private function resolveActiveEmployeePositionForEmployee(int $employeeId, int $positionId, int $employmentId): ?EmployeePosition
    {
        $today = now()->toDateString();

        return EmployeePosition::query()
            ->where('employee_id', $employeeId)
            ->where('employee_employment_id', $employmentId)
            ->whereNull('deleted_at')
            ->whereHas('position', function ($query) use ($positionId): void {
                $query->whereKey($positionId);
            })
            ->where(function ($dateScope) use ($today): void {
                $dateScope->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->with('position:id,title')
            ->first();
    }

    private function resolveAssignmentLinkedPositionTitle(EmployeeAssignment $assignment): ?string
    {
        $today = now()->toDateString();

        /** @var Collection<int, AssignmentPosition> $links */
        $links = $assignment->assignmentPositions;

        $active = $links->filter(function (AssignmentPosition $ap) use ($today): bool {
            if ($ap->deleted_at !== null) {
                return false;
            }
            if ($ap->employeePosition === null || $ap->employeePosition->position === null) {
                return false;
            }
            if ($ap->end_date === null) {
                return true;
            }

            return $ap->end_date->toDateString() >= $today;
        });

        if ($active->isEmpty()) {
            return null;
        }

        $primary = $active->firstWhere('is_primary_for_assignment', true);
        $chosen = $primary ?? $active->sort(function (AssignmentPosition $a, AssignmentPosition $b): int {
            $da = $a->start_date?->getTimestamp() ?? 0;
            $db = $b->start_date?->getTimestamp() ?? 0;
            if ($da !== $db) {
                return $db <=> $da;
            }

            return $b->id <=> $a->id;
        })->first();

        $title = $chosen?->employeePosition?->position?->title;

        return is_string($title) && $title !== '' ? $title : null;
    }
}
