<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexMyEmployeeOvertimesRequest;
use App\Services\BranchContextService;
use App\Services\MyEmployeeOvertimesPageService;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeMyController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private MyEmployeeOvertimesPageService $myEmployeeOvertimesPageService,
    ) {}

    public function __invoke(IndexMyEmployeeOvertimesRequest $request): Response
    {
        $employeeIdRaw = $request->user()?->employee_id;
        $employeeId = $employeeIdRaw !== null ? (int) $employeeIdRaw : null;

        $built = $this->myEmployeeOvertimesPageService->buildPage($request, $employeeId);

        $organization = $this->branchContextService->defaultOrganization();

        return Inertia::render('Overtime/My', [
            'myEmployeeOvertimes' => $built['paginator'],
            'myOvertimeFilters' => $request->inertiaMyOvertimeFilters(),
            'overtimePolicyOptions' => $this->myEmployeeOvertimesPageService->overtimePolicyOptions($organization),
            'myOvertimeKpis' => $built['kpis'],
            'hasEmployeeRecord' => $employeeId !== null,
        ]);
    }
}
