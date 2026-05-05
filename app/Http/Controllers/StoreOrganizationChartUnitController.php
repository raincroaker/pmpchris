<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationChartUnitRequest;
use App\Services\OrganizationChartUnitMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StoreOrganizationChartUnitController extends Controller
{
    public function __invoke(
        StoreOrganizationChartUnitRequest $request,
        OrganizationChartUnitMutationService $mutationService,
    ): JsonResponse {
        try {
            $createdUnit = $mutationService->createUnit(
                $request->user(),
                $request->chartBranchId(),
                $request->nodeId(),
                $request->unitTypeName(),
                $request->unitName(),
                $request->unitCode(),
                $request->areaId(),
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $createdUnit,
            'message' => 'Unit created successfully.',
        ]);
    }
}
