<?php

namespace App\Http\Controllers;

use App\Services\BranchContextService;
use App\Services\HolidayViewDataService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayCalendarController extends Controller
{
    public function __invoke(Request $request, HolidayViewDataService $holidayData): Response
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        $displayMonth = $this->resolveDisplayMonth($request);

        if ($organization === null) {
            return Inertia::render('Calendar/Holidays', [
                'holidayTypes' => [],
                'organizationHolidays' => [],
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        $organizationId = (int) $organization->id;

        return Inertia::render('Calendar/Holidays', [
            'holidayTypes' => $holidayData->holidayTypesForOrganization($organizationId),
            'organizationHolidays' => $holidayData->organizationHolidaysForOrganization($organizationId, $displayMonth),
            'calendarDisplayMonth' => $displayMonth->format('Y-m'),
        ]);
    }

    private function resolveDisplayMonth(Request $request): CarbonInterface
    {
        $raw = $request->query('month');
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $raw)->startOfMonth();
            } catch (\Throwable) {
                return now()->startOfMonth();
            }
        }

        return now()->startOfMonth();
    }
}
