<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Support\EmployeeBranchDirectoryFilter;
use Illuminate\Http\Request;

class ScheduleAssignmentAccessService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * Whether the user may view and manage schedule assignments and work-schedule UI for the current workspace branch.
     *
     * Org-wide HR roles always allowed. Branch HR managers only when assigned to manage the active branch root.
     */
    public function allows(?User $user, Request $request): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ])) {
            return true;
        }

        if (! $user->hasRole(Role::CODE_HR_MANAGER)) {
            return false;
        }

        $branchContext = $this->branchContextService->workspaceBranchContext($request);
        if ($branchContext === null) {
            return false;
        }

        $managedRootIds = $this->branchContextService->managedBranchRootIdsFor($user);

        return in_array((int) $branchContext['id'], $managedRootIds, true);
    }

    /**
     * Same rules as {@see \App\Http\Controllers\EmployeesIndexController} for branch-picker session root.
     */
    public function resolveBranchRootForEmployeeDirectory(Request $request, Organization $organization): ?int
    {
        $user = $request->user();
        if ($user === null || ! $user->mustSelectBranch()) {
            return null;
        }

        $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);
        if ($branchId <= 0 || ! $this->branchContextService->isValidSessionBranchId($branchId)) {
            return null;
        }

        $root = $this->branchContextService->findSelectableBranchRoot($branchId, $organization);
        if ($root === null || (int) $root->organization_id !== (int) $organization->id) {
            return null;
        }

        return $branchId;
    }

    public function employeeBelongsToDirectory(Employee $employee, Organization $organization, Request $request): bool
    {
        $today = now()->toDateString();
        $orgId = (int) $organization->id;
        $branchRootId = $this->resolveBranchRootForEmployeeDirectory($request, $organization);

        return Employee::query()
            ->whereKey($employee->id)
            ->whereNull('employees.deleted_at')
            ->where(function ($query) use ($orgId, $branchRootId, $today): void {
                EmployeeBranchDirectoryFilter::apply($query, $orgId, $branchRootId, $today);
            })
            ->exists();
    }

    /**
     * Organizational unit subtree ids reachable from {$branchRootId} (includes the root row).
     *
     * @return list<int>
     */
    public function collectSubtreeUnitIds(int $organizationId, int $branchRootId): array
    {
        $ids = [$branchRootId];
        $frontier = [$branchRootId];

        while ($frontier !== []) {
            $children = OrganizationalUnit::query()
                ->where('organization_id', $organizationId)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if ($children === []) {
                break;
            }

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $frontier)));
        }

        return $ids;
    }

    /**
     * Organizational units selectable on HR leave/OT team forms: full subtree under the workspace
     * root (including that branch row), excluding only the org-level Head Office apex row when it
     * appears inside that subtree — not the workspace branch itself.
     *
     * @return list<int>
     */
    public function teamHrFormSelectableUnitIds(int $organizationId, int $workspaceRootUnitId): array
    {
        $ids = $this->collectSubtreeUnitIds($organizationId, $workspaceRootUnitId);
        $headOfficeApexId = $this->organizationHeadOfficeApexUnitId($organizationId);
        if ($headOfficeApexId === null) {
            return $ids;
        }

        return array_values(array_filter(
            $ids,
            static fn (int $id): bool => $id !== $headOfficeApexId
        ));
    }

    /**
     * Parent-null Head Office unit for the org (org umbrella), when that unit type exists.
     */
    private function organizationHeadOfficeApexUnitId(int $organizationId): ?int
    {
        $headOfficeTypeId = UnitType::query()->where('name', 'Head Office')->value('id');
        if ($headOfficeTypeId === null) {
            return null;
        }

        $id = OrganizationalUnit::query()
            ->where('organization_id', $organizationId)
            ->whereNull('parent_id')
            ->where('unit_type_id', $headOfficeTypeId)
            ->where('is_active', true)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
