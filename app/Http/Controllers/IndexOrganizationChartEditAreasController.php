<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Organization;
use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;

class IndexOrganizationChartEditAreasController extends Controller
{
    public function __invoke(
        BranchContextService $branchContextService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            return response()->json([
                'data' => [],
            ]);
        }

        $areas = Area::query()
            ->where('organization_id', $organization->id)
            ->orderByRaw('LOWER(code)')
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'code', 'name', 'is_active'])
            ->map(fn (Area $area): array => [
                'id' => (int) $area->id,
                'code' => (string) ($area->code ?? ''),
                'name' => (string) $area->name,
                'is_active' => (bool) $area->is_active,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $areas,
        ]);
    }
}
