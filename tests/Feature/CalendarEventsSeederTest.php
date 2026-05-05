<?php

use App\Models\BranchCalendarEvent;
use App\Models\CompanyCalendarEvent;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\TeamCalendarEvent;
use Database\Seeders\CalendarEventsSeeder;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calendar events seeder creates requested company, branch, and team scoped rows', function () {
    $this->seed([
        OrganizationalStructureSeeder::class,
        RoleSeeder::class,
        DemoCooperativeSeeder::class,
        DevelopmentUserSeeder::class,
        CalendarEventsSeeder::class,
    ]);

    $organization = Organization::query()
        ->where('code', 'PMPC')
        ->firstOrFail();

    $panRoot = OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'PAN')
        ->firstOrFail();
    $tagRoot = OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'TAG')
        ->firstOrFail();

    $units = OrganizationalUnit::query()
        ->where('organization_id', $organization->id)
        ->where('is_active', true)
        ->get(['id', 'parent_id']);

    /** @var array<int, list<int>> $childrenByParent */
    $childrenByParent = [];
    foreach ($units as $unit) {
        $parentId = $unit->parent_id !== null ? (int) $unit->parent_id : 0;
        if (! isset($childrenByParent[$parentId])) {
            $childrenByParent[$parentId] = [];
        }
        $childrenByParent[$parentId][] = (int) $unit->id;
    }

    $collect = function (int $rootId) use ($childrenByParent): array {
        $queue = [$rootId];
        $ids = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (! is_int($current) || in_array($current, $ids, true)) {
                continue;
            }

            $ids[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $ids;
    };

    $expectedPanAndTagUnitCount = count(array_unique(array_merge(
        $collect((int) $panRoot->id),
        $collect((int) $tagRoot->id),
    )));

    expect(CompanyCalendarEvent::query()->where('organization_id', $organization->id)->count('*'))->toBe(2);

    expect(BranchCalendarEvent::query()
        ->where('organization_id', $organization->id)
        ->where('root_unit_id', $panRoot->id)
        ->count('*'))->toBe(2);

    expect(BranchCalendarEvent::query()
        ->where('organization_id', $organization->id)
        ->where('root_unit_id', $tagRoot->id)
        ->count('*'))->toBe(2);

    expect(TeamCalendarEvent::query()
        ->where('organization_id', $organization->id)
        ->whereNull('root_unit_id', 'and', false)
        ->whereNull('unit_id', 'and', false)
        ->count('*'))->toBe(1);

    expect(TeamCalendarEvent::query()
        ->where('organization_id', $organization->id)
        ->whereNotNull('root_unit_id', 'and')
        ->whereNotNull('unit_id', 'and')
        ->count('*'))->toBe($expectedPanAndTagUnitCount);
});
