<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationChartEditAreaRequest;
use App\Models\Area;
use App\Models\Organization;
use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationChartEditAreaController extends Controller
{
    public function __invoke(
        UpdateOrganizationChartEditAreaRequest $request,
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

        if (Area::query()
            ->where('organization_id', $organization->id)
            ->whereKeyNot($area->id)
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $request->input('name'))])
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => 'An area with this name already exists.',
            ]);
        }

        $area->code = (string) $request->input('code');
        $area->name = (string) $request->input('name');
        $area->save();

        return response()->json([
            'data' => [
                'id' => (int) $area->id,
                'code' => (string) ($area->code ?? ''),
                'name' => (string) $area->name,
                'is_active' => (bool) $area->is_active,
            ],
        ]);
    }
}
