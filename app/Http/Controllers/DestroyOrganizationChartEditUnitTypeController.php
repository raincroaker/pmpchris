<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyOrganizationChartEditUnitTypeRequest;
use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class DestroyOrganizationChartEditUnitTypeController extends Controller
{
    public function __invoke(
        DestroyOrganizationChartEditUnitTypeRequest $request,
        UnitType $unitType,
    ): RedirectResponse {
        if (OrganizationalUnit::query()->where('unit_type_id', $unitType->id)->exists()) {
            throw ValidationException::withMessages([
                'unit_type' => __('Cannot delete this type while organizational units use it.'),
            ]);
        }

        $unitType->delete();

        return redirect()->back();
    }
}
