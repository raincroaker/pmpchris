<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

final readonly class EmployeeTeamHrPagesAccess
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * Whether the user may view Employee Leaves / Employee Overtime team pages and team-hr JSON for the current workspace branch.
     *
     * Allowed: {@see Role::CODE_SUPER_ADMIN}, {@see Role::CODE_HR_HEAD}, or {@see Role::CODE_HR_MANAGER} when the workspace branch root is in their managed branch roots.
     */
    public function allows(?User $user, Request $request): bool
    {
        return $this->mayAccessTeamLeaveOvertime($user, $request);
    }

    /**
     * Whether the user may add, edit, or delete team leave / overtime records (same gate as {@see allows} today).
     */
    public function allowsAddingTeamLeaveOvertimeEntries(?User $user, Request $request): bool
    {
        return $this->mayAccessTeamLeaveOvertime($user, $request);
    }

    private function mayAccessTeamLeaveOvertime(?User $user, Request $request): bool
    {
        if ($user === null) {
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

        $context = $this->branchContextService->workspaceBranchContext($request);
        if ($context === null) {
            return false;
        }

        $managedRootIds = $this->branchContextService->managedBranchRootIdsFor($user);

        return in_array((int) $context['id'], $managedRootIds, true);
    }
}
