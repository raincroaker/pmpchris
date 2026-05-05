<?php

namespace App\Http\Controllers;

use App\Models\OvertimePolicy;
use App\Services\BranchContextService;
use App\Services\LeaveOvertimePolicyManagementAccess;
use Inertia\Inertia;
use Inertia\Response;

class OvertimePoliciesController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private LeaveOvertimePolicyManagementAccess $leaveOvertimePolicyManagementAccess,
    ) {}

    public function __invoke(): Response
    {
        abort_unless($this->leaveOvertimePolicyManagementAccess->allows(request()->user()), 403);

        $organization = $this->branchContextService->defaultOrganization();

        $overtimePolicies = [];
        if ($organization !== null) {
            $overtimePolicies = OvertimePolicy::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->orderBy('code')
                ->get()
                ->map(static fn (OvertimePolicy $policy): array => $policy->toPolicyPageProps())
                ->values()
                ->all();
        }

        return Inertia::render('Overtime/Policies', [
            'overtimePolicies' => $overtimePolicies,
        ]);
    }
}
