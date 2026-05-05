<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyPositionRequest;
use App\Models\Position;
use App\Services\PositionMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DestroyPositionController extends Controller
{
    public function __construct(
        private PositionMutationService $positionMutationService,
    ) {}

    public function __invoke(DestroyPositionRequest $request, Position $position): JsonResponse
    {
        try {
            $result = $this->positionMutationService->deletePosition($position);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
