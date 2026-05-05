<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\TeamCalendarEvent;
use App\Services\CalendarEventMutationService;
use Illuminate\Http\JsonResponse;

class UpdateTeamCalendarEventController extends Controller
{
    public function __invoke(
        UpdateCalendarEventRequest $request,
        TeamCalendarEvent $teamCalendarEvent,
        CalendarEventMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->updateTeamEvent(
            $teamCalendarEvent,
            $request,
            $request->user()?->id,
        );

        return response()->json(['data' => $result]);
    }
}
