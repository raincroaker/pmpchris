<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\BranchCalendarEvent;
use App\Services\CalendarEventMutationService;
use Illuminate\Http\JsonResponse;

class UpdateBranchCalendarEventController extends Controller
{
    public function __invoke(
        UpdateCalendarEventRequest $request,
        BranchCalendarEvent $branchCalendarEvent,
        CalendarEventMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->updateBranchEvent(
            $branchCalendarEvent,
            $request,
            $request->user()?->id,
        );

        return response()->json(['data' => $result]);
    }
}
