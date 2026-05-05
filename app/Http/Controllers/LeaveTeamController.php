<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTeamHrEmployeeLeavesRequest;
use App\Services\BranchContextService;
use App\Services\EmployeeTeamHrPagesAccess;
use App\Services\TeamHrEmployeeLeavesPageService;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTeamController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private EmployeeTeamHrPagesAccess $employeeTeamHrPagesAccess,
        private TeamHrEmployeeLeavesPageService $teamHrEmployeeLeavesPageService,
    ) {}

    public function __invoke(IndexTeamHrEmployeeLeavesRequest $request): Response
    {
        abort_unless($this->employeeTeamHrPagesAccess->allows($request->user(), $request), 403);

        $organization = $this->branchContextService->defaultOrganization();
        $built = $this->teamHrEmployeeLeavesPageService->buildPage($request);

        return Inertia::render('Leave/Team', [
            'teamEmployeeLeaves' => $built['paginator'],
            'leaveEmployeeFilterOptions' => $built['employeeFilterOptions'],
            'leaveTeamFilters' => $built['filters'],
            'leavePolicyOptions' => $this->teamHrEmployeeLeavesPageService->leavePolicyOptions($organization),
        ]);
    }
}
