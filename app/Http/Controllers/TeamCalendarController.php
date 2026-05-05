<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Services\BranchContextService;
use App\Services\CalendarBirthdayService;
use App\Services\CalendarViewDataService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamCalendarController extends Controller
{
    public function __invoke(Request $request, CalendarViewDataService $calendarData, CalendarBirthdayService $birthdays): Response
    {
        $user = $request->user();
        $service = app(BranchContextService::class);
        $selectedBranchRootId = 0;
        $displayMonth = $this->resolveDisplayMonth($request);

        if ($service->canSwitchBranchContext($user)) {
            $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);
            if ($branchId > 0 && $service->isValidSessionBranchId($branchId, $user)) {
                $selectedBranchRootId = $branchId;
            }
        } elseif ($user?->employee_id !== null) {
            $organization = $service->defaultOrganization();
            if ($organization !== null) {
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
        }

        if ($selectedBranchRootId <= 0) {
            return Inertia::render('Calendar/Team', [
                'calendarCategories' => [],
                'teamEvents' => [],
                'teamBirthdays' => [],
                'teamCalendarUnits' => [],
                'selectedTeamUnitId' => null,
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        $organization = $service->defaultOrganization();
        if ($organization === null) {
            return Inertia::render('Calendar/Team', [
                'calendarCategories' => [],
                'teamEvents' => [],
                'teamBirthdays' => [],
                'teamCalendarUnits' => [],
                'selectedTeamUnitId' => null,
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        $activeUnits = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->with('unitType:id,name')
            ->get(['id', 'parent_id', 'code', 'name', 'unit_type_id']);

        if ($activeUnits->isEmpty()) {
            return Inertia::render('Calendar/Team', [
                'calendarCategories' => $calendarData->categoriesForOrganization((int) $organization->id),
                'teamEvents' => [],
                'teamBirthdays' => [],
                'teamCalendarUnits' => [],
                'selectedTeamUnitId' => null,
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        /** @var array<int, list<OrganizationalUnit>> $unitsByParentId */
        $unitsByParentId = [];
        foreach ($activeUnits as $unit) {
            $parentId = $unit->parent_id !== null ? (int) $unit->parent_id : 0;
            if (! isset($unitsByParentId[$parentId])) {
                $unitsByParentId[$parentId] = [];
            }
            $unitsByParentId[$parentId][] = $unit;
        }

        /** @var array<int, OrganizationalUnit> $unitById */
        $unitById = [];
        foreach ($activeUnits as $unit) {
            $unitById[(int) $unit->id] = $unit;
        }

        if (! isset($unitById[$selectedBranchRootId])) {
            return Inertia::render('Calendar/Team', [
                'calendarCategories' => $calendarData->categoriesForOrganization((int) $organization->id),
                'teamEvents' => [],
                'teamBirthdays' => [],
                'teamCalendarUnits' => [],
                'selectedTeamUnitId' => null,
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        $queue = [$selectedBranchRootId];
        /** @var list<int> $subtreeUnitIds */
        $subtreeUnitIds = [];

        while ($queue !== []) {
            $currentId = array_shift($queue);
            if (! is_int($currentId)) {
                continue;
            }

            if (in_array($currentId, $subtreeUnitIds, true)) {
                continue;
            }

            $subtreeUnitIds[] = $currentId;

            $children = $unitsByParentId[$currentId] ?? [];
            usort($children, fn (OrganizationalUnit $a, OrganizationalUnit $b): int => strcasecmp((string) $a->name, (string) $b->name));

            foreach ($children as $child) {
                $childId = (int) $child->id;
                $queue[] = $childId;
            }
        }

        if ($subtreeUnitIds === []) {
            return Inertia::render('Calendar/Team', [
                'calendarCategories' => $calendarData->categoriesForOrganization((int) $organization->id),
                'teamEvents' => [],
                'teamBirthdays' => [],
                'teamCalendarUnits' => [],
                'selectedTeamUnitId' => null,
                'calendarDisplayMonth' => $displayMonth->format('Y-m'),
            ]);
        }

        $activeAssignedUnitIds = [];
        if ($user?->employee_id !== null) {
            $today = now()->toDateString();

            $activeAssignments = EmployeeAssignment::query()
                ->where('employee_id', $user->employee_id)
                ->whereIn('organizational_unit_id', $subtreeUnitIds, 'and', false)
                ->whereNull('deleted_at', 'and', false)
                ->where(function (Builder $query) use ($today): void {
                    $query->whereNull('end_date', 'and', false)
                        ->orWhereDate('end_date', '>=', $today);
                })
                ->get(['organizational_unit_id', 'is_head']);

            $activeAssignedUnitIds = $activeAssignments
                ->pluck('organizational_unit_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $isSuperAdminOrHrHead = $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;
        $isHrManager = $user?->hasRole(Role::CODE_HR_MANAGER) ?? false;
        $managedBranchRootIds = $isHrManager && $user !== null
            ? $service->managedBranchRootIdsFor($user)
            : [];
        $isManagedCurrentBranch = in_array($selectedBranchRootId, $managedBranchRootIds, true);

        /** @var list<int> $visibleUnitIds */
        $visibleUnitIds = [];
        foreach ($subtreeUnitIds as $unitId) {
            $unit = $unitById[$unitId] ?? null;
            if (! $unit instanceof OrganizationalUnit) {
                continue;
            }

            $isAssignedToUnit = in_array($unitId, $activeAssignedUnitIds, true);
            $isSelectedScopeRootUnit = $unitId === $selectedBranchRootId;

            if ($isSelectedScopeRootUnit) {
                if ($isSuperAdminOrHrHead || ($isHrManager && $isManagedCurrentBranch) || $isAssignedToUnit) {
                    $visibleUnitIds[] = $unitId;
                }

                continue;
            }

            if ($isSuperAdminOrHrHead) {
                $visibleUnitIds[] = $unitId;

                continue;
            }

            if ($isHrManager && $isManagedCurrentBranch) {
                $visibleUnitIds[] = $unitId;

                continue;
            }

            if ($isAssignedToUnit) {
                $visibleUnitIds[] = $unitId;
            }
        }

        // Defensive guard: keep selected root visible for privileged users (or assignees)
        // even if upstream branching/filtering changes miss it.
        if (
            in_array($selectedBranchRootId, $subtreeUnitIds, true)
            && ! in_array($selectedBranchRootId, $visibleUnitIds, true)
            && ($isSuperAdminOrHrHead || in_array($selectedBranchRootId, $activeAssignedUnitIds, true))
        ) {
            $visibleUnitIds[] = $selectedBranchRootId;
        }

        usort($visibleUnitIds, function (int $a, int $b) use ($unitById, $selectedBranchRootId): int {
            if ($a === $selectedBranchRootId && $b !== $selectedBranchRootId) {
                return -1;
            }
            if ($b === $selectedBranchRootId && $a !== $selectedBranchRootId) {
                return 1;
            }

            $aUnit = $unitById[$a] ?? null;
            $bUnit = $unitById[$b] ?? null;

            return strcasecmp((string) $aUnit?->name, (string) $bUnit?->name);
        });

        $hasActiveOrganizationAssignment = false;
        if ($user?->employee_id !== null) {
            $today = now()->toDateString();
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

        /** @var list<array{id: int, code: string, name: string, canCreateEvent: bool, isOrganizationScope?: bool}> $teamCalendarUnits */
        $teamCalendarUnits = [];
        if ($isSuperAdminOrHrHead || $hasActiveOrganizationAssignment) {
            $teamCalendarUnits[] = [
                'id' => -1 * (int) $organization->id,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
                'canCreateEvent' => $isSuperAdminOrHrHead,
                'isOrganizationScope' => true,
            ];
        }

        foreach ($visibleUnitIds as $unitId) {
            $unit = $unitById[$unitId] ?? null;
            if (! $unit instanceof OrganizationalUnit) {
                continue;
            }

            $isSelectedScopeRootUnit = $unitId === $selectedBranchRootId;
            $canCreateEvent = false;
            if ($isSelectedScopeRootUnit) {
                $canCreateEvent = $isSuperAdminOrHrHead || ($isHrManager && $isManagedCurrentBranch);
            } elseif ($isSuperAdminOrHrHead || ($isHrManager && $isManagedCurrentBranch)) {
                $canCreateEvent = true;
            }

            $teamCalendarUnits[] = [
                'id' => $unitId,
                'code' => (string) $unit->code,
                'name' => (string) $unit->name,
                'canCreateEvent' => $canCreateEvent,
            ];
        }

        $allowedUnitOptionIds = collect($teamCalendarUnits)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $requestedSelectedTeamUnitId = (int) $request->query('unit_id', 0);
        $selectedTeamUnitId = in_array($requestedSelectedTeamUnitId, $allowedUnitOptionIds, true)
            ? $requestedSelectedTeamUnitId
            : ($teamCalendarUnits[0]['id'] ?? null);

        $visibleUnitIdsForEvents = [];
        $hasOrganizationScopeOption = false;
        if (is_int($selectedTeamUnitId)) {
            if ($selectedTeamUnitId < 0) {
                $hasOrganizationScopeOption = true;
            } elseif ($selectedTeamUnitId > 0) {
                $visibleUnitIdsForEvents = [$selectedTeamUnitId];
            }
        }

        return Inertia::render('Calendar/Team', [
            'calendarCategories' => $calendarData->categoriesForOrganization((int) $organization->id),
            'teamEvents' => $calendarData->teamEventsForUnits(
                (int) $organization->id,
                $selectedBranchRootId,
                $visibleUnitIdsForEvents,
                $hasOrganizationScopeOption,
                $displayMonth,
            ),
            'teamBirthdays' => $birthdays->teamBirthdaysForScope(
                (int) $organization->id,
                $visibleUnitIdsForEvents,
                $hasOrganizationScopeOption,
                $subtreeUnitIds,
                $displayMonth,
            ),
            'teamCalendarUnits' => $teamCalendarUnits,
            'selectedTeamUnitId' => $selectedTeamUnitId,
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
