<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexMyEmployeeLeavesRequest;
use App\Services\BranchContextService;
use App\Services\MyEmployeeLeavesPageService;
use Inertia\Inertia;
use Inertia\Response;

class LeaveMyController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private MyEmployeeLeavesPageService $myEmployeeLeavesPageService,
    ) {}

    public function __invoke(IndexMyEmployeeLeavesRequest $request): Response
    {
        $employeeIdRaw = $request->user()?->employee_id;
        $employeeId = $employeeIdRaw !== null ? (int) $employeeIdRaw : null;

        $built = $this->myEmployeeLeavesPageService->buildPage($request, $employeeId);

        $organization = $this->branchContextService->defaultOrganization();

        return Inertia::render('Leave/My', [
            'myEmployeeLeaves' => $built['paginator'],
            'myLeaveFilters' => $request->inertiaMyLeaveFilters(),
            'leavePolicyOptions' => $this->myEmployeeLeavesPageService->leavePolicyOptions($organization),
            'myLeaveKpis' => $built['kpis'],
            'hasEmployeeRecord' => $employeeId !== null,
        ]);
    }
}
