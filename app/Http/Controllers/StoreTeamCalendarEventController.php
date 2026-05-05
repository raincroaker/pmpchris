<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarEventRequest;
use App\Services\CalendarEventMutationService;
use Illuminate\Http\JsonResponse;

class StoreTeamCalendarEventController extends Controller
{
    public function __invoke(
        StoreCalendarEventRequest $request,
        CalendarEventMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->createTeamEvent($request, $request->user()?->id);

        return response()->json(['data' => $result], 201);
    }
}
