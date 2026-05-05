<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarEventCategoryRequest;
use App\Services\CalendarEventCategoryMutationService;
use Illuminate\Http\JsonResponse;

class StoreCalendarEventCategoryController extends Controller
{
    public function __invoke(
        StoreCalendarEventCategoryRequest $request,
        CalendarEventCategoryMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->createCategory(
            $request->categoryName(),
            $request->colorKey(),
            $request->user()?->id,
        );

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
