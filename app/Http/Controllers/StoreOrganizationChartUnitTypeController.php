<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationChartUnitTypeRequest;
use App\Services\OrganizationChartEditStructureMutationService;
use Illuminate\Http\RedirectResponse;

class StoreOrganizationChartUnitTypeController extends Controller
{
    public function __invoke(
        StoreOrganizationChartUnitTypeRequest $request,
        OrganizationChartEditStructureMutationService $mutationService,
    ): RedirectResponse {
        $payload = $request->unitTypePayload();
        $mutationService->createUnitType(
            $payload['name'],
            $payload['description'],
            $payload['color'],
            $payload['can_be_root'],
            $payload['parent_type_ids'],
        );

        return redirect()->route(
            'organization-chart.edit',
            $request->preservedListingQuery(),
        );
    }
}
