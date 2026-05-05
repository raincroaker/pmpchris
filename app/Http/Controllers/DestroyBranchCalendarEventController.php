<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCalendarEventRequest;
use App\Models\BranchCalendarEvent;
use App\Services\CalendarEventMutationService;
use Illuminate\Http\JsonResponse;

class DestroyBranchCalendarEventController extends Controller
{
    public function __invoke(
        DestroyCalendarEventRequest $request,
        BranchCalendarEvent $branchCalendarEvent,
        CalendarEventMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->deleteBranchEvent($branchCalendarEvent, $request);

        return response()->json(['data' => $result]);
    }
}
