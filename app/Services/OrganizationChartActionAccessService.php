<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class OrganizationChartActionAccessService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * Conservative capability flags when the chart shows multiple root branches together.
     * Branch-scoped destructive actions rely on {@see resolveChartRootForRequest()} and remain disabled here.
     *
     * @return array{canManageBranch: bool, canManageOrganizationNode: bool}
     */
    public function chartCapabilitiesForOverallChart(?User $user): array
    {
        return [
            'canManageBranch' => false,
            'canManageOrganizationNode' => false,
        ];
    }

    public function resolveChartRootForRequest(Request $request): ?OrganizationalUnit
    {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            return null;
        }

        $user = $request->user();
        $chartBranchId = (int) $request->integer('chart_branch_id', 0);

        if ($chartBranchId > 0) {
            $localRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
            if ($localRoot instanceof OrganizationalUnit) {
                return $localRoot;
            }
        }

        if ($user instanceof User && $user->mustSelectBranch()) {
            $sessionBranchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);

            return $this->branchContextService->findSelectableBranchRoot($sessionBranchId, $organization, $user);
        }

        return $this->branchContextService->defaultChartRoot($organization);
    }

    public function canManageChartBranch(?User $user, ?OrganizationalUnit $chartRoot): bool
    {
        if (! $user instanceof User || ! $chartRoot instanceof OrganizationalUnit) {
            return false;
        }

        if ($user->hasAnyRole([Role::CODE_SUPER_ADMIN, Role::CODE_HR_HEAD])) {
            return true;
        }

        if (! $user->hasRole(Role::CODE_HR_MANAGER)) {
            return false;
        }

        $managedRootIds = $this->branchContextService->managedBranchRootIdsFor($user);

        return in_array((int) $chartRoot->id, $managedRootIds, true);
    }

    public function canManageNode(?User $user, ?OrganizationalUnit $chartRoot, ?OrganizationalUnit $node): bool
    {
        if (! $this->canManageChartBranch($user, $chartRoot)) {
            return false;
        }

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole(Role::CODE_HR_MANAGER) && ! $user->hasAnyRole([Role::CODE_SUPER_ADMIN, Role::CODE_HR_HEAD])) {
            // HR Managers are view-only on organization-level (non-unit) nodes.
            if (! $node instanceof OrganizationalUnit) {
                return false;
            }
        }

        if (! $node instanceof OrganizationalUnit || ! $chartRoot instanceof OrganizationalUnit) {
            return true;
        }

        return $this->isNodeWithinRoot($node, $chartRoot);
    }

    public function canManageOrganizationNode(?User $user, ?OrganizationalUnit $chartRoot): bool
    {
        if (! $this->canManageChartBranch($user, $chartRoot)) {
            return false;
        }

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasAnyRole([Role::CODE_SUPER_ADMIN, Role::CODE_HR_HEAD])) {
            return true;
        }

        return false;
    }

    public function findNodeWithinRootById(?OrganizationalUnit $chartRoot, int $nodeId): ?OrganizationalUnit
    {
        if (! $chartRoot instanceof OrganizationalUnit || $nodeId <= 0) {
            return null;
        }

        $node = OrganizationalUnit::query()
            ->where('organization_id', $chartRoot->organization_id)
            ->where('is_active', true)
            ->find($nodeId);

        if (! $node instanceof OrganizationalUnit) {
            return null;
        }

        return $this->isNodeWithinRoot($node, $chartRoot) ? $node : null;
    }

    private function isNodeWithinRoot(OrganizationalUnit $node, OrganizationalUnit $root): bool
    {
        if ((int) $node->id === (int) $root->id) {
            return true;
        }

        $parentId = $node->parent_id;
        while ($parentId !== null) {
            if ((int) $parentId === (int) $root->id) {
                return true;
            }

            $parentId = OrganizationalUnit::query()
                ->where('organization_id', $root->organization_id)
                ->where('is_active', true)
                ->whereKey($parentId)
                ->value('parent_id');
        }

        return false;
    }
}
