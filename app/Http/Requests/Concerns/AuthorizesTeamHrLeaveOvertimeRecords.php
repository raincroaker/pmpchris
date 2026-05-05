<?php

namespace App\Http\Requests\Concerns;

use App\Models\Organization;
use App\Services\BranchContextService;
use App\Services\EmployeeTeamHrPagesAccess;

trait AuthorizesTeamHrLeaveOvertimeRecords
{
    protected function userMayMutateTeamHrRecords(): bool
    {
        return app(EmployeeTeamHrPagesAccess::class)->allowsAddingTeamLeaveOvertimeEntries($this->user(), $this);
    }

    protected function defaultOrganization(): ?Organization
    {
        return app(BranchContextService::class)->defaultOrganization();
    }

    protected function workspaceBranchRootId(): ?int
    {
        $context = app(BranchContextService::class)->workspaceBranchContext($this);

        return $context !== null ? (int) $context['id'] : null;
    }
}
