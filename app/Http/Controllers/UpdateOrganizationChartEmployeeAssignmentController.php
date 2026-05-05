<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationChartEmployeeAssignmentRequest;
use App\Models\EmployeeAssignment;
use App\Services\OrganizationChartEmployeeAssignmentMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationChartEmployeeAssignmentController extends Controller
{
    public function __invoke(
        UpdateOrganizationChartEmployeeAssignmentRequest $request,
        EmployeeAssignment $assignment,
        OrganizationChartEmployeeAssignmentMutationService $mutationService,
    ): JsonResponse {
        try {
            $updated = $mutationService->updateAssignment(
                $request->user(),
                $request->chartBranchId(),
                $request->nodeId(),
                $assignment,
                $request->positionId(),
                $request->isHead(),
                $request->isPrimary(),
                $request->effectiveDate(),
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $updated,
            'message' => 'Employee assignment updated successfully.',
        ]);
    }
}
