<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTeamHrOrganizationHolidayRulesRequest;
use App\Services\BranchContextService;
use App\Services\HolidayViewDataService;
use Illuminate\Http\JsonResponse;

class IndexTeamHrOrganizationHolidayRulesController extends Controller
{
    public function __invoke(
        IndexTeamHrOrganizationHolidayRulesRequest $request,
        BranchContextService $branchContextService,
        HolidayViewDataService $holidayViewDataService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();

        if ($organization === null) {
            abort(404);
        }

        if ($branchContextService->workspaceBranchContext($request) === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'organization_id' => (int) $organization->id,
                ],
            ]);
        }

        $rules = $holidayViewDataService->organizationHolidayRulesIntersectingClosedRange(
            (int) $organization->id,
            $request->dateFromYmd(),
            $request->dateToYmd(),
        );

        return response()->json([
            'data' => $rules,
            'meta' => [
                'organization_id' => (int) $organization->id,
            ],
        ]);
    }
}
