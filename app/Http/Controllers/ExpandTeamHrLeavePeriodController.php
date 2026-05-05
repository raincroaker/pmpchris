<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpandTeamHrLeavePeriodRequest;
use App\Models\User;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use App\Services\TeamHrLeavePeriodExpansionService;
use App\Support\TeamHrFormEligibleEmployeeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ExpandTeamHrLeavePeriodController extends Controller
{
    public function __invoke(
        ExpandTeamHrLeavePeriodRequest $request,
        BranchContextService $branchContextService,
        ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
        TeamHrLeavePeriodExpansionService $leavePeriodExpansionService,
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
            ['workScheduleTemplate'],
        );

        if ($employee === null) {
            abort(404);
        }

        if ($employee->work_schedule_template_id === null || $employee->workScheduleTemplate === null) {
            throw ValidationException::withMessages([
                'employee_id' => ['Assign a work schedule to this employee before expanding a leave period.'],
            ]);
        }

        $schedule = $employee->workScheduleTemplate;

        if ((int) $schedule->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $from = CarbonImmutable::parse($request->dateFromYmd())->startOfDay();
        $to = CarbonImmutable::parse($request->dateToYmd())->startOfDay();

        $payload = $leavePeriodExpansionService->expandClosedRangeUsingTemplate(
            $schedule,
            (int) $organization->id,
            $from,
            $to,
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
