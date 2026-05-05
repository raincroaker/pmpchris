<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyOrganizationHolidayRequest;
use App\Models\OrganizationHoliday;
use App\Services\OrganizationHolidayMutationService;
use Illuminate\Http\JsonResponse;

class DestroyOrganizationHolidayController extends Controller
{
    public function __invoke(
        DestroyOrganizationHolidayRequest $request,
        OrganizationHoliday $organizationHoliday,
        OrganizationHolidayMutationService $mutationService,
    ): JsonResponse {
        $result = $mutationService->delete($organizationHoliday);

        return response()->json([
            'data' => $result,
        ]);
    }
}
