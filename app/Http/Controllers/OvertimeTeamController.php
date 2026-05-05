<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTeamHrEmployeeOvertimesRequest;
use App\Services\BranchContextService;
use App\Services\EmployeeTeamHrPagesAccess;
use App\Services\TeamHrEmployeeOvertimesPageService;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeTeamController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private EmployeeTeamHrPagesAccess $employeeTeamHrPagesAccess,
        private TeamHrEmployeeOvertimesPageService $teamHrEmployeeOvertimesPageService,
    ) {}

    public function __invoke(IndexTeamHrEmployeeOvertimesRequest $request): Response
    {
        abort_unless($this->employeeTeamHrPagesAccess->allows($request->user(), $request), 403);

        $organization = $this->branchContextService->defaultOrganization();
        $built = $this->teamHrEmployeeOvertimesPageService->buildPage($request);

        return Inertia::render('Overtime/Team', [
            'teamEmployeeOvertimes' => $built['paginator'],
            'overtimeTeamFilters' => $built['filters'],
            'overtimePolicyOptions' => $this->teamHrEmployeeOvertimesPageService->overtimePolicyOptions($organization),
        ]);
    }
}
