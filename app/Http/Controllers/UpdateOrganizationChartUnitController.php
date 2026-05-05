<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationChartUnitRequest;
use App\Models\OrganizationalUnit;
use App\Services\OrganizationChartUnitMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationChartUnitController extends Controller
{
    public function __invoke(
        UpdateOrganizationChartUnitRequest $request,
        OrganizationalUnit $unit,
        OrganizationChartUnitMutationService $mutationService,
    ): JsonResponse {
        try {
            $updatedUnit = $mutationService->updateUnit(
                $request->user(),
                $request->chartBranchId(),
                $request->nodeId(),
                $unit,
                $request->unitName(),
                $request->unitCode(),
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
            'message' => 'Unit updated successfully.',
        ]);
    }
}
