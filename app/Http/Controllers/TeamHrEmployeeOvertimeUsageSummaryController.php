<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTeamHrEmployeeOvertimeUsageSummaryRequest;
use App\Models\User;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use App\Services\TeamHrEmployeeOvertimeUsageSummaryService;
use App\Support\TeamHrFormEligibleEmployeeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class TeamHrEmployeeOvertimeUsageSummaryController extends Controller
{
    public function __invoke(
        IndexTeamHrEmployeeOvertimeUsageSummaryRequest $request,
        BranchContextService $branchContextService,
        ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
        TeamHrEmployeeOvertimeUsageSummaryService $summaryService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();
        if ($organization === null) {
            abort(404);
        }

        $workspace = $branchContextService->workspaceBranchContext($request);
        if ($workspace === null || (int) $workspace['id'] !== $request->chartBranchId()) {
            abort(403);
        }

        $branchRootId = (int) $workspace['id'];
        $allowedIds = $scheduleAssignmentAccessService->teamHrFormSelectableUnitIds((int) $organization->id, $branchRootId);
        $unitId = $request->unitId();

        if (! in_array($unitId, $allowedIds, true)) {
            abort(404);
        }

        /** @var User|null $actingUser */
        $actingUser = $request->user();
        $employee = TeamHrFormEligibleEmployeeResolver::firstOrNull(
            $actingUser,
            $request->employeeId(),
            (int) $organization->id,
            $branchRootId,
            $unitId,
        );

        if ($employee === null) {
            abort(404);
        }

        $asOf = CarbonImmutable::now((string) config('app.timezone'))->startOfDay();
        $payload = $summaryService->build(
            (int) $organization->id,
            $employee->id,
            $request->policyCodeTrimmed(),
            $request->excludeEmployeeOvertimeId(),
            $asOf,
        );

        return response()->json([
            'data' => $payload,
            'meta' => [
                'organization_id' => (int) $organization->id,
                'employee_id' => $employee->id,
            ],
        ]);
    }
}
