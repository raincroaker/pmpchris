<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePositionRequest;
use App\Models\Position;
use App\Services\PositionMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdatePositionController extends Controller
{
    public function __construct(
        private PositionMutationService $positionMutationService,
    ) {}

    public function __invoke(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        try {
            $result = $this->positionMutationService->updatePosition(
                $position,
                (string) $request->string('code'),
                (string) $request->string('title'),
                $request->filled('description') ? (string) $request->string('description') : null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
