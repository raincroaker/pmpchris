<?php

namespace App\Http\Controllers;

use App\Models\LeavePolicy;
use App\Services\BranchContextService;
use App\Services\LeaveOvertimePolicyManagementAccess;
use Inertia\Inertia;
use Inertia\Response;

class LeavePoliciesController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private LeaveOvertimePolicyManagementAccess $leaveOvertimePolicyManagementAccess,
    ) {}

    public function __invoke(): Response
    {
        abort_unless($this->leaveOvertimePolicyManagementAccess->allows(request()->user()), 403);

        $organization = $this->branchContextService->defaultOrganization();

        $leavePolicies = [];
        if ($organization !== null) {
            $leavePolicies = LeavePolicy::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->orderBy('code')
                ->get()
                ->map(static fn (LeavePolicy $policy): array => $policy->toPolicyPageProps())
                ->values()
                ->all();
        }

        return Inertia::render('Leave/Policies', [
            'leavePolicies' => $leavePolicies,
        ]);
    }
}
