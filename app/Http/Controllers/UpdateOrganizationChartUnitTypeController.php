<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationChartUnitTypeRequest;
use App\Models\UnitType;
use App\Services\OrganizationChartEditStructureMutationService;
use Illuminate\Http\RedirectResponse;

class UpdateOrganizationChartUnitTypeController extends Controller
{
    public function __invoke(
        UpdateOrganizationChartUnitTypeRequest $request,
        UnitType $unitType,
        OrganizationChartEditStructureMutationService $mutationService,
    ): RedirectResponse {
        $payload = $request->unitTypePayload();
        $mutationService->updateUnitType(
            $unitType,
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
