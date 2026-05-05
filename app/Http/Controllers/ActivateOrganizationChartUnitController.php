<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateOrganizationChartUnitRequest;
use App\Models\OrganizationalUnit;
use App\Services\OrganizationChartUnitMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ActivateOrganizationChartUnitController extends Controller
{
    public function __invoke(
        ActivateOrganizationChartUnitRequest $request,
        OrganizationalUnit $unit,
        OrganizationChartUnitMutationService $mutationService,
    ): JsonResponse {
        try {
            $updatedUnit = $mutationService->activateUnit(
                $request->user(),
                $request->chartBranchId(),
                $request->nodeId(),
                $unit,
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $updatedUnit,
            'message' => 'Unit activated successfully.',
        ]);
    }
}
