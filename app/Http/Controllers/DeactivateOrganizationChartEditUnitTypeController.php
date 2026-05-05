<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeactivateOrganizationChartEditUnitTypeRequest;
use App\Models\UnitType;
use Illuminate\Http\RedirectResponse;

class DeactivateOrganizationChartEditUnitTypeController extends Controller
{
    public function __invoke(
        DeactivateOrganizationChartEditUnitTypeRequest $request,
        UnitType $unitType,
    ): RedirectResponse {
        if ($unitType->is_active) {
            $unitType->update(['is_active' => false]);
        }

        return redirect()->back();
    }
}
