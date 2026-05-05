<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationHolidayRequest;
use App\Models\OrganizationHoliday;
use App\Services\OrganizationHolidayMutationService;
use Illuminate\Http\JsonResponse;

class UpdateOrganizationHolidayController extends Controller
{
    public function __invoke(
        UpdateOrganizationHolidayRequest $request,
        OrganizationHoliday $organizationHoliday,
        OrganizationHolidayMutationService $mutationService,
    ): JsonResponse {
        $data = $mutationService->update(
            $organizationHoliday,
            $request->holidayName(),
            $request->startDate(),
            $request->endDate(),
            $request->typeSlug(),
            $request->notes(),
            $request->recurrencePayload(),
            $request->user()?->id,
        );

        return response()->json([
            'data' => $data,
        ]);
    }
}
