<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Services\PositionMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StorePositionController extends Controller
{
    public function __construct(
        private PositionMutationService $positionMutationService,
    ) {}

    public function __invoke(StorePositionRequest $request): JsonResponse
    {
        try {
            $result = $this->positionMutationService->createPosition(
                (string) $request->string('code'),
                (string) $request->string('title'),
                $request->filled('description') ? (string) $request->string('description') : null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
