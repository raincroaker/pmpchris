<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationChartEditAreaRequest;
use App\Models\Area;
use App\Models\Organization;
use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StoreOrganizationChartEditAreaController extends Controller
{
    public function __invoke(
        StoreOrganizationChartEditAreaRequest $request,
        BranchContextService $branchContextService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            abort(404);
        }

        if (Area::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $request->input('name'))])
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => 'An area with this name already exists.',
            ]);
        }

        $area = Area::query()->create([
            'organization_id' => $organization->id,
            'code' => (string) $request->input('code'),
            'name' => (string) $request->input('name'),
            'is_active' => true,
        ]);

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
