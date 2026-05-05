<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckOrganizationChartUnitCodeAvailabilityRequest;
use App\Services\OrganizationChartUnitMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CheckOrganizationChartUnitCodeAvailabilityController extends Controller
{
    public function __invoke(
        CheckOrganizationChartUnitCodeAvailabilityRequest $request,
        OrganizationChartUnitMutationService $mutationService,
    ): JsonResponse {
        try {
            $result = $mutationService->checkCodeAvailability(
                $request->user(),
                $request->chartBranchId(),
                $request->nodeId(),
                $request->code(),
                $request->ignoreUnitId(),
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'code' => $result,
        ]);
    }
}
