<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHolidayTypeRequest;
use App\Services\HolidayTypeMutationService;
use Illuminate\Http\JsonResponse;

class UpdateHolidayTypeController extends Controller
{
    public function __invoke(
        UpdateHolidayTypeRequest $request,
        string $holiday_type_slug,
        HolidayTypeMutationService $mutationService,
    ): JsonResponse {
        $type = $mutationService->findManagedTypeBySlug($holiday_type_slug);

        $name = $type->kind === 'builtin'
            ? null
            : ($request->optionalNameForCustomType() ?? $type->name);

        $data = $mutationService->updateType(
            $type,
            $name,
            $request->colorKey(),
            $request->payPolicy(),
            $request->customMultiplier(),
            $request->premiumNote(),
            $request->user()?->id,
        );

        return response()->json([
            'data' => $data,
        ]);
    }
}
