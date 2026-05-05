<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeactivatePositionRequest;
use App\Models\Position;
use App\Services\PositionMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DeactivatePositionController extends Controller
{
    public function __construct(
        private PositionMutationService $positionMutationService,
    ) {}

    public function __invoke(DeactivatePositionRequest $request, Position $position): JsonResponse
    {
        try {
            $result = $this->positionMutationService->deactivatePosition($position);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
