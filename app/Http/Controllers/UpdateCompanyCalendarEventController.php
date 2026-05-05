<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\CompanyCalendarEvent;
use App\Services\CalendarEventMutationService;
use Illuminate\Http\JsonResponse;

class UpdateCompanyCalendarEventController extends Controller
{
    public function __invoke(
        UpdateCalendarEventRequest $request,
        CompanyCalendarEvent $companyCalendarEvent,
        CalendarEventMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->updateCompanyEvent(
            $companyCalendarEvent,
            $request,
            $request->user()?->id,
        );

        return response()->json(['data' => $result]);
    }
}
