<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCalendarEventCategoryRequest;
use App\Models\CalendarEventCategory;
use App\Services\CalendarEventCategoryMutationService;
use Illuminate\Http\JsonResponse;

class UpdateCalendarEventCategoryController extends Controller
{
    public function __invoke(
        UpdateCalendarEventCategoryRequest $request,
        CalendarEventCategory $calendarEventCategory,
        CalendarEventCategoryMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->updateCategory(
            $calendarEventCategory,
            $request->categoryName(),
            $request->colorKey(),
            $request->user()?->id,
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
