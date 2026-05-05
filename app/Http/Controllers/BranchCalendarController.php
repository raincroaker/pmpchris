<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAffiliation;
use App\Services\BranchContextService;
use App\Services\CalendarBirthdayService;
use App\Services\CalendarViewDataService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchCalendarController extends Controller
{
    public function __invoke(Request $request, CalendarViewDataService $calendarData, CalendarBirthdayService $birthdays): Response
    {
        $user = $request->user();
        $service = app(BranchContextService::class);
        $organization = $service->defaultOrganization();
        $displayMonth = $this->resolveDisplayMonth($request);

        if ($organization === null) {
            return Inertia::render('Calendar/Branch', [
                'calendarCategories' => [],
                'branchEvents' => [],
                'branchBirthdays' => [],
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        $selectedBranchRootId = 0;
        if ($service->canSwitchBranchContext($user)) {
            $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);
            if ($branchId > 0 && $service->isValidSessionBranchId($branchId, $user)) {
                $selectedBranchRootId = $branchId;
            }
        } elseif ($user?->employee_id !== null) {
            $today = now()->toDateString();
            $rootIds = EmployeeAffiliation::query()
                ->where('employee_id', $user->employee_id)
                ->where('organization_id', $organization->id)
                ->whereNotNull('root_unit_id', 'and')
                ->whereNull('deleted_at', 'and', false)
                ->where(function (Builder $query) use ($today): void {
                    $query->whereNull('end_date', 'and', false)
                        ->orWhereDate('end_date', '>=', $today);
                })
                ->orderByDesc('is_primary')
                ->orderBy('id', 'asc')
                ->pluck('root_unit_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (count($rootIds) === 1) {
                $selectedBranchRootId = $rootIds[0];
            }
        }

        $categories = $calendarData->categoriesForOrganization((int) $organization->id);
        $events = $selectedBranchRootId > 0
            ? $calendarData->branchEventsForRoot((int) $organization->id, $selectedBranchRootId, $displayMonth)
            : [];
        $branchBirthdays = $selectedBranchRootId > 0
            ? $birthdays->branchBirthdaysForRoot((int) $organization->id, $selectedBranchRootId, $displayMonth)
            : [];

        return Inertia::render('Calendar/Branch', [
            'calendarCategories' => $categories,
            'branchEvents' => $events,
            'branchBirthdays' => $branchBirthdays,
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
