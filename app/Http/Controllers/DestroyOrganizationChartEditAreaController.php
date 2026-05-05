<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyOrganizationChartEditAreaRequest;
use App\Models\Area;
use App\Models\Organization;
use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;

class DestroyOrganizationChartEditAreaController extends Controller
{
    public function __invoke(
        DestroyOrganizationChartEditAreaRequest $request,
        Area $area,
        BranchContextService $branchContextService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            abort(404);
        }

        if ((int) $area->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $area->delete();

        return response()->json([
            'message' => 'Area deleted.',
        ]);
    }
}
