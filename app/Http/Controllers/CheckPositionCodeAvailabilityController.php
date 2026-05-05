<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckPositionCodeAvailabilityRequest;
use App\Services\PositionMutationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class CheckPositionCodeAvailabilityController extends Controller
{
    public function __invoke(
        CheckPositionCodeAvailabilityRequest $request,
        PositionMutationService $positionMutationService,
    ): JsonResponse {
        try {
            $result = $positionMutationService->checkCodeAvailability(
                $request->code(),
                $request->ignorePositionId(),
            );
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return response()->json([
            'code' => $result,
        ]);
    }
}
