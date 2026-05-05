<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAssignment;
use App\Models\Role;
use App\Services\BranchContextService;
use App\Services\CalendarViewDataService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamCalendarEventsIndexController extends Controller
{
    public function __invoke(Request $request, CalendarViewDataService $calendarData): JsonResponse
    {
        $service = app(BranchContextService::class);
        $organization = $service->defaultOrganization();
        if ($organization === null) {
            return response()->json([
                'data' => [],
                'meta' => ['nextPage' => null, 'hasMore' => false],
            ]);
        }

        $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);
        if ($branchId <= 0) {
            return response()->json([
                'data' => [],
                'meta' => ['nextPage' => null, 'hasMore' => false],
            ]);
        }

        $user = $request->user();
        $selectedUnitId = (int) $request->integer('unit_id', 0);
        $includeOrgScope = false;
        $unitIds = [];

        if ($selectedUnitId < 0) {
            $today = now()->toDateString();
            $isSuperAdminOrHrHead = $user?->hasAnyRole([Role::CODE_SUPER_ADMIN, Role::CODE_HR_HEAD]) ?? false;
            $hasActiveOrganizationAssignment = false;
            if ($user?->employee_id !== null) {
                $hasActiveOrganizationAssignment = EmployeeAssignment::query()
                    ->where('employee_id', $user->employee_id)
                    ->where('organization_id', $organization->id)
                    ->whereNull('organizational_unit_id', 'and', false)
                    ->whereNull('deleted_at', 'and', false)
                    ->where(function (Builder $query) use ($today): void {
                        $query->whereNull('end_date', 'and', false)
                            ->orWhereDate('end_date', '>=', $today);
                    })
                    ->exists();
            }

            $includeOrgScope = $isSuperAdminOrHrHead || $hasActiveOrganizationAssignment;
        } elseif ($selectedUnitId > 0) {
            $unitIds = [$selectedUnitId];
        }

        $displayMonth = $this->resolveDisplayMonth($request);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(200, (int) $request->integer('per_page', 40)));
        $result = $calendarData->teamEventsPageForUnits(
            (int) $organization->id,
            $branchId,
            $unitIds,
            $includeOrgScope,
            $displayMonth,
            $page,
            $perPage,
            $this->resolveListFilters($request),
        );

        return response()->json([
            'data' => $result['data'],
            'meta' => [
                'nextPage' => $result['nextPage'],
                'hasMore' => $result['hasMore'],
            ],
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

    /**
     * @return array{search?: string, categoryIds?: list<int>, range?: string, customFrom?: string|null, customTo?: string|null}
     */
    private function resolveListFilters(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $range = trim((string) $request->query('range', 'month'));
        $rangeAllowed = ['today', 'week', 'month', 'year', 'custom'];
        if (! in_array($range, $rangeAllowed, true)) {
            $range = 'month';
        }

        $customFrom = trim((string) $request->query('custom_from', ''));
        $customTo = trim((string) $request->query('custom_to', ''));
        $categoryIds = [];
        $rawCategoryIds = $request->query('category_ids');
        if (is_string($rawCategoryIds) && $rawCategoryIds !== '') {
            $categoryIds = array_values(array_filter(
                array_map(fn (string $id): int => (int) trim($id), explode(',', $rawCategoryIds)),
                fn (int $id): bool => $id > 0,
            ));
        }

        return [
            'search' => $search,
            'categoryIds' => $categoryIds,
            'range' => $range,
            'customFrom' => $customFrom !== '' ? $customFrom : null,
            'customTo' => $customTo !== '' ? $customTo : null,
        ];
    }
}
