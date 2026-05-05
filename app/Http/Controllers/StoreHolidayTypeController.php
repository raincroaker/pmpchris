<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayTypeRequest;
use App\Services\HolidayTypeMutationService;
use Illuminate\Http\JsonResponse;

class StoreHolidayTypeController extends Controller
{
    public function __invoke(StoreHolidayTypeRequest $request, HolidayTypeMutationService $mutationService): JsonResponse
    {
        $data = $mutationService->createCustomType(
            $request->typeName(),
            $request->colorKey(),
            $request->payPolicy(),
            $request->customMultiplier(),
            $request->premiumNote(),
            $request->user()?->id,
        );

        return response()->json(['data' => $data], 201);
    }
}
