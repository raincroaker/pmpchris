<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationChartEmployeeAssignmentRequest;
use App\Services\OrganizationChartEmployeeAssignmentMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StoreOrganizationChartEmployeeAssignmentController extends Controller
{
    public function __invoke(
        StoreOrganizationChartEmployeeAssignmentRequest $request,
        OrganizationChartEmployeeAssignmentMutationService $mutationService,
    ): JsonResponse {
        try {
            $created = $mutationService->createAssignment(
                $request->user(),
                $request->chartBranchId(),
                $request->nodeId(),
                $request->employeeId(),
                $request->positionId(),
                $request->isHead(),
                $request->isPrimary(),
                $request->startDate(),
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $created,
            'message' => 'Employee assignment created successfully.',
        ]);
    }
}
