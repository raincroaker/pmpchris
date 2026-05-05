<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class LeaveOvertimePolicyManagementAccess
{
    /**
     * Users who may open Leave / Overtime policy index pages and view catalog rows
     * (matches {@see \App\Http\Middleware\HandleInertiaRequests::canShowAdministrationNav} HR-admin roles;
     * policy nav uses `adminOnly`).
     */
    public function allows(?User $user): bool
    {
        return $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
            Role::CODE_HR_MANAGER,
        ]) ?? false;
    }

    /**
     * Users who may create, update, or delete leave and overtime policies.
     */
    public function allowsMutating(?User $user): bool
    {
        return $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;
    }
}
