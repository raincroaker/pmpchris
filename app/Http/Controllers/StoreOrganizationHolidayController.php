<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationHolidayRequest;
use App\Services\OrganizationHolidayMutationService;
use Illuminate\Http\JsonResponse;

class StoreOrganizationHolidayController extends Controller
{
    public function __invoke(
        StoreOrganizationHolidayRequest $request,
        OrganizationHolidayMutationService $mutationService,
    ): JsonResponse {
        $data = $mutationService->create(
            $request->holidayName(),
            $request->startDate(),
            $request->endDate(),
            $request->typeSlug(),
            $request->notes(),
            $request->recurrencePayload(),
            $request->user()?->id,
        );

        return response()->json(['data' => $data], 201);
    }
}
