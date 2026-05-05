<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyHolidayTypeRequest;
use App\Services\HolidayTypeMutationService;
use Illuminate\Http\JsonResponse;

class DestroyHolidayTypeController extends Controller
{
    public function __invoke(
        DestroyHolidayTypeRequest $request,
        string $holiday_type_slug,
        HolidayTypeMutationService $mutationService,
    ): JsonResponse {
        $type = $mutationService->findManagedTypeBySlug($holiday_type_slug);
        $result = $mutationService->deleteType($type);

        return response()->json([
            'data' => $result,
        ]);
    }
}
