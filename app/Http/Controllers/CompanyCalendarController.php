<?php

namespace App\Http\Controllers;

use App\Services\BranchContextService;
use App\Services\CalendarViewDataService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyCalendarController extends Controller
{
    public function __invoke(Request $request, CalendarViewDataService $calendarData): Response
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        $displayMonth = $this->resolveDisplayMonth($request);
        if ($organization === null) {
            return Inertia::render('Calendar/Company', [
                'calendarCategories' => [],
                'companyEvents' => [],
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        return Inertia::render('Calendar/Company', [
            'calendarCategories' => $calendarData->categoriesForOrganization((int) $organization->id),
            'companyEvents' => $calendarData->companyEventsForOrganization((int) $organization->id, $displayMonth),
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
