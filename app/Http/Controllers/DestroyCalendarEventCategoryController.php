<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCalendarEventCategoryRequest;
use App\Models\CalendarEventCategory;
use App\Services\CalendarEventCategoryMutationService;
use Illuminate\Http\JsonResponse;

class DestroyCalendarEventCategoryController extends Controller
{
    public function __invoke(
        DestroyCalendarEventCategoryRequest $request,
        CalendarEventCategory $calendarEventCategory,
        CalendarEventCategoryMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->deleteCategory($calendarEventCategory);

        return response()->json([
            'data' => $result,
        ]);
    }
}
