<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

final class EmploymentWorkMutationAccess
{
    public function __construct(
        private EmployeeEmploymentDirectoryVisibility $directoryVisibility,
        private EmployeeTeamHrPagesAccess $teamHrPagesAccess,
    ) {}

    /**
     * Mutations follow directory visibility for HR staff ({@see Role::CODE_SUPER_ADMIN}, {@see Role::CODE_HR_HEAD},
     * {@see Role::CODE_HR_MANAGER}). {@see Role::CODE_HR_MANAGER} must additionally pass {@see EmployeeTeamHrPagesAccess}
     * for the current workspace branch. Linked employees — including on About Me — have no workspace self-service edits.
     */
    public function allows(Request $request, EmployeeEmployment $employment): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        if (! $this->isHrRoleUser($user)) {
            return false;
        }

        if (! $this->teamHrPagesAccess->allows($user, $request)) {
            return false;
        }

        return $this->directoryVisibility->isEmploymentEmployeeVisible($request, $employment);
    }

    /**
     * HR staff only: whether the viewer may edit employment/work payloads visible under directory scope
     * (aligned with HR directory routes: {@see EmployeeTeamHrPagesAccess} plus directory visibility).
     */
    public function mayHrManageDirectoryVisibleEmployee(Request $request, Employee $employee): bool
    {
        $user = $request->user();
        if ($user === null || ! $this->isHrRoleUser($user)) {
            return false;
        }

        if (! $this->teamHrPagesAccess->allows($user, $request)) {
            return false;
        }

        return $this->directoryVisibility->isEmployeeVisibleInDirectory($request, $employee);
    }

    /**
     * Recording employment separation is limited to org-wide HR roles; branch HR managers may adjust hire dates only.
     */
    public function mayRecordEmploymentSeparation(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);
    }

    private function isHrRoleUser(User $user): bool
    {
        return $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
            Role::CODE_HR_MANAGER,
        ]);
    }
}
