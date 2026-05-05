<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndexTeamHrFormUnitsController extends Controller
{
    /**
     * Organizational units under the workspace root (including the branch row), excluding only
     * the org-level Head Office apex when it appears in that subtree.
     */
    public function __invoke(
        Request $request,
        BranchContextService $branchContextService,
        ScheduleAssignmentAccessService $scheduleAssignmentAccessService,
    ): JsonResponse {
        $organization = $branchContextService->defaultOrganization();
        if ($organization === null) {
            abort(404);
        }

        $workspace = $branchContextService->workspaceBranchContext($request);
        if ($workspace === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'workspace_branch_id' => null,
                ],
            ]);
        }

        $branchRootId = (int) $workspace['id'];
        $selectableIds = $scheduleAssignmentAccessService->teamHrFormSelectableUnitIds((int) $organization->id, $branchRootId);

        if ($selectableIds === []) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'workspace_branch_id' => $branchRootId,
                ],
            ]);
        }

        $units = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $selectableIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id']);

        $orderedUnits = $this->orderUnitsForFilter($units, $branchRootId);

        return response()->json([
            'data' => $orderedUnits->map(static function (OrganizationalUnit $unit): array {
                return [
                    'id' => (int) $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'parent_id' => $unit->parent_id !== null ? (int) $unit->parent_id : null,
                ];
            })->values()->all(),
            'meta' => [
                'workspace_branch_id' => $branchRootId,
            ],
        ]);
    }

    /**
     * Keep toolbar unit order aligned with Employees index: branch root first, then descendants.
     *
     * @param  EloquentCollection<int, OrganizationalUnit>  $units
     * @return EloquentCollection<int, OrganizationalUnit>
     */
    private function orderUnitsForFilter(EloquentCollection $units, ?int $branchRootId): EloquentCollection
    {
        $byParent = $units
            ->groupBy(fn (OrganizationalUnit $unit): string => (string) ($unit->parent_id ?? 'root'));
        $ordered = collect();
        $seen = [];

        $walk = function (int $id) use (&$walk, $byParent, $units, &$ordered, &$seen): void {
            if (isset($seen[$id])) {
                return;
            }
            $unit = $units->firstWhere('id', $id);
            if ($unit === null) {
                return;
            }

            $seen[$id] = true;
            $ordered->push($unit);

            foreach ($byParent->get((string) $id, collect()) as $child) {
                $walk((int) $child->id);
            }
        };

        if ($branchRootId !== null && $units->firstWhere('id', $branchRootId) !== null) {
            $walk($branchRootId);

            return new EloquentCollection($ordered->all());
        }

        foreach ($byParent->get('root', collect()) as $root) {
            $walk((int) $root->id);
        }

        foreach ($units as $unit) {
            $walk((int) $unit->id);
        }

        return new EloquentCollection($ordered->all());
    }
}
