<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteOrganizationChartEmployeeAssignmentRequest;
use App\Models\EmployeeAssignment;
use App\Services\OrganizationChartEmployeeAssignmentMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DeleteOrganizationChartEmployeeAssignmentController extends Controller
{
    public function __invoke(
        DeleteOrganizationChartEmployeeAssignmentRequest $request,
        EmployeeAssignment $assignment,
        OrganizationChartEmployeeAssignmentMutationService $mutationService,
    ): JsonResponse {
        try {
            $mutationService->removeAssignment(
                $request->user(),
                $request->chartBranchId(),
                $request->nodeId(),
                $assignment,
                $request->endDate(),
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'message' => 'Employee assignment removed successfully.',
        ]);
    }
}
