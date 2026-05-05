<?php

namespace App\Http\Requests\Concerns;

use App\Services\BranchContextService;
use App\Services\LeaveOvertimePolicyManagementAccess;

trait AuthorizesLeaveOvertimePolicyManagement
{
    /**
     * Create, update, or delete policies (super admin and HR head only).
     */
    protected function userMayMutatePolicies(): bool
    {
        return app(LeaveOvertimePolicyManagementAccess::class)->allowsMutating($this->user());
    }

    protected function defaultOrganizationId(): ?int
    {
        $organization = app(BranchContextService::class)->defaultOrganization();

        return $organization?->id;
    }
}
