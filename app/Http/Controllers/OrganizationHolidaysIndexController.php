<?php

namespace App\Http\Controllers;

use App\Services\BranchContextService;
use App\Services\HolidayViewDataService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationHolidaysIndexController extends Controller
{
    public function __invoke(Request $request, HolidayViewDataService $holidayData): JsonResponse
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        if ($organization === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'nextPage' => null,
                    'hasMore' => false,
                ],
            ]);
        }

        $displayMonth = $this->resolveDisplayMonth($request);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(200, (int) $request->integer('per_page', 100)));
        $result = $holidayData->organizationHolidaysPageForOrganization(
            (int) $organization->id,
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
     * @return array{search?: string, typeIds?: list<string>, range?: string, customFrom?: string|null, customTo?: string|null}
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
        $typeIds = [];
        $rawTypeIds = $request->query('type_ids');
        if (is_string($rawTypeIds) && $rawTypeIds !== '') {
            $typeIds = array_values(array_filter(
                array_map(fn (string $id): string => trim($id), explode(',', $rawTypeIds)),
                fn (string $id): bool => $id !== '',
            ));
        }

        return [
            'search' => $search,
            'typeIds' => $typeIds,
            'range' => $range,
            'customFrom' => $customFrom !== '' ? $customFrom : null,
            'customTo' => $customTo !== '' ? $customTo : null,
        ];
    }
}
