<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCalendarEventRequest;
use App\Models\CompanyCalendarEvent;
use App\Services\CalendarEventMutationService;
use Illuminate\Http\JsonResponse;

class DestroyCompanyCalendarEventController extends Controller
{
    public function __invoke(
        DestroyCalendarEventRequest $request,
        CompanyCalendarEvent $companyCalendarEvent,
        CalendarEventMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->deleteCompanyEvent($companyCalendarEvent, $request);

        return response()->json(['data' => $result]);
    }
}
