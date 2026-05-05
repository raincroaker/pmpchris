<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCalendarEventRequest;
use App\Models\TeamCalendarEvent;
use App\Services\CalendarEventMutationService;
use Illuminate\Http\JsonResponse;

class DestroyTeamCalendarEventController extends Controller
{
    public function __invoke(
        DestroyCalendarEventRequest $request,
        TeamCalendarEvent $teamCalendarEvent,
        CalendarEventMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->deleteTeamEvent($teamCalendarEvent, $request);

        return response()->json(['data' => $result]);
    }
}
