<?php

namespace Database\Seeders;

use App\Models\BranchCalendarEvent;
use App\Models\CalendarEventCategory;
use App\Models\CompanyCalendarEvent;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\TeamCalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CalendarEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('code', 'PMPC')
            ->where('is_active', true)
            ->first();

        if (! $organization instanceof Organization) {
            return;
        }

        $actorId = User::query()
            ->whereIn('email', ['superadmin@example.com', 'hrhead1@example.com'], 'and', false)
            ->orderBy('id', 'asc')
            ->value('id');

        $categoryIds = $this->seedCategories((int) $organization->id, $actorId !== null ? (int) $actorId : null);
        $this->seedCompanyEvents((int) $organization->id, $categoryIds, $actorId !== null ? (int) $actorId : null);
        $this->seedBranchEvents((int) $organization->id, $categoryIds, $actorId !== null ? (int) $actorId : null);
        $this->seedTeamEvents((int) $organization->id, $categoryIds, $actorId !== null ? (int) $actorId : null);
    }

    /**
     * @return array<string, int>
     */
    private function seedCategories(int $organizationId, ?int $actorId): array
    {
        $rows = [
            ['name' => 'Planning', 'color_key' => 'blue'],
            ['name' => 'Meeting', 'color_key' => 'indigo'],
            ['name' => 'Review', 'color_key' => 'violet'],
            ['name' => 'Training', 'color_key' => 'fuchsia'],
            ['name' => 'Deadline', 'color_key' => 'rose'],
            ['name' => 'Announcement', 'color_key' => 'teal'],
            ['name' => 'Operations', 'color_key' => 'emerald'],
            ['name' => 'Compliance', 'color_key' => 'rose'],
            ['name' => 'Incident', 'color_key' => 'orange'],
            ['name' => 'Leave', 'color_key' => 'emerald'],
            ['name' => 'Team Building', 'color_key' => 'teal'],
            ['name' => 'Audit', 'color_key' => 'slate'],
        ];

        /** @var array<string, int> $ids */
        $ids = [];

        foreach ($rows as $row) {
            $slug = Str::slug($row['name']);

            $category = CalendarEventCategory::query()->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'slug' => $slug,
                ],
                [
                    'name' => $row['name'],
                    'color_key' => $row['color_key'],
                    'is_active' => true,
                    'created_by_user_id' => $actorId,
                    'updated_by_user_id' => $actorId,
                ],
            );

            $ids[$slug] = (int) $category->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $categoryIds
     */
    private function seedCompanyEvents(int $organizationId, array $categoryIds, ?int $actorId): void
    {
        $events = [
            [
                'title' => 'Company All-Hands Forum',
                'starts_at' => CarbonImmutable::create(2026, 5, 5, 10, 0, 0),
                'ends_at' => CarbonImmutable::create(2026, 5, 5, 11, 30, 0),
                'location' => 'Main Hall',
                'details' => 'Leadership updates and open Q&A.',
                'category_slug' => 'announcement',
                'recurrence' => null,
            ],
            [
                'title' => 'Payroll Cutoff Reminder',
                'starts_at' => CarbonImmutable::create(2026, 5, 15, 16, 0, 0),
                'ends_at' => CarbonImmutable::create(2026, 5, 15, 16, 30, 0),
                'location' => 'Finance Office',
                'details' => 'Monthly payroll cutoff reminder for all departments.',
                'category_slug' => 'deadline',
                'recurrence' => [
                    'frequency' => 'monthly',
                    'interval' => 1,
                    'ends' => ['type' => 'count', 'count' => 6],
                ],
            ],
        ];

        foreach ($events as $event) {
            CompanyCalendarEvent::query()->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'title' => $event['title'],
                    'starts_at' => $event['starts_at'],
                ],
                [
                    'category_id' => $categoryIds[$event['category_slug']] ?? $categoryIds['announcement'],
                    'ends_at' => $event['ends_at'],
                    'is_all_day' => false,
                    'location' => $event['location'],
                    'details' => $event['details'],
                    'recurrence' => $event['recurrence'],
                    'set_by_user_id' => $actorId,
                    'last_edited_by_user_id' => $actorId,
                ],
            );
        }
    }

    /**
     * @param  array<string, int>  $categoryIds
     */
    private function seedBranchEvents(int $organizationId, array $categoryIds, ?int $actorId): void
    {
        $rootsByCode = OrganizationalUnit::query()
            ->where('organization_id', $organizationId)
            ->whereNull('parent_id', 'and', false)
            ->whereIn('code', ['PAN', 'TAG'], 'and', false)
            ->where('is_active', true)
            ->get(['id', 'code'])
            ->keyBy('code');

        $branchRows = [
            'PAN' => [
                [
                    'title' => 'Panabo Branch Operations Briefing',
                    'starts_at' => CarbonImmutable::create(2026, 5, 8, 8, 30, 0),
                    'ends_at' => CarbonImmutable::create(2026, 5, 8, 9, 0, 0),
                    'location' => 'PAN Conference Room',
                    'details' => 'Daily branch priorities and service updates.',
                    'category_slug' => 'meeting',
                    'recurrence' => [
                        'frequency' => 'weekly',
                        'interval' => 1,
                        'byWeekday' => [1, 3, 5],
                        'ends' => ['type' => 'until', 'date' => '2026-05-31'],
                    ],
                ],
                [
                    'title' => 'Panabo Member Engagement Session',
                    'starts_at' => CarbonImmutable::create(2026, 5, 18, 14, 0, 0),
                    'ends_at' => CarbonImmutable::create(2026, 5, 18, 15, 30, 0),
                    'location' => 'PAN Lobby Area',
                    'details' => 'Branch-level member feedback and engagement session.',
                    'category_slug' => 'team-building',
                    'recurrence' => null,
                ],
            ],
            'TAG' => [
                [
                    'title' => 'Tagum Branch Morning Huddle',
                    'starts_at' => CarbonImmutable::create(2026, 5, 9, 8, 30, 0),
                    'ends_at' => CarbonImmutable::create(2026, 5, 9, 9, 0, 0),
                    'location' => 'TAG Operations Bay',
                    'details' => 'Shift kickoff and branch support alignment.',
                    'category_slug' => 'meeting',
                    'recurrence' => [
                        'frequency' => 'daily',
                        'interval' => 1,
                        'ends' => ['type' => 'count', 'count' => 10],
                    ],
                ],
                [
                    'title' => 'Tagum Branch Compliance Checkpoint',
                    'starts_at' => CarbonImmutable::create(2026, 5, 19, 10, 0, 0),
                    'ends_at' => CarbonImmutable::create(2026, 5, 19, 11, 0, 0),
                    'location' => 'TAG Training Room',
                    'details' => 'Branch compliance follow-up and action tracking.',
                    'category_slug' => 'compliance',
                    'recurrence' => null,
                ],
            ],
        ];

        foreach ($branchRows as $code => $events) {
            $root = $rootsByCode->get($code);
            if (! $root instanceof OrganizationalUnit) {
                continue;
            }

            foreach ($events as $event) {
                BranchCalendarEvent::query()->updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'root_unit_id' => (int) $root->id,
                        'title' => $event['title'],
                        'starts_at' => $event['starts_at'],
                    ],
                    [
                        'category_id' => $categoryIds[$event['category_slug']] ?? $categoryIds['meeting'],
                        'ends_at' => $event['ends_at'],
                        'is_all_day' => false,
                        'location' => $event['location'],
                        'details' => $event['details'],
                        'recurrence' => $event['recurrence'],
                        'set_by_user_id' => $actorId,
                        'last_edited_by_user_id' => $actorId,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, int>  $categoryIds
     */
    private function seedTeamEvents(int $organizationId, array $categoryIds, ?int $actorId): void
    {
        // Highest organization-level Team event (no root/unit scope).
        TeamCalendarEvent::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'root_unit_id' => null,
                'unit_id' => null,
                'title' => 'Organization Team Leadership Sync',
                'starts_at' => CarbonImmutable::create(2026, 5, 3, 9, 0, 0),
            ],
            [
                'category_id' => $categoryIds['meeting'] ?? $categoryIds['announcement'],
                'ends_at' => CarbonImmutable::create(2026, 5, 3, 10, 0, 0),
                'is_all_day' => false,
                'location' => 'PMPC Main Board Room',
                'details' => 'Cross-unit leadership alignment at organization level.',
                'recurrence' => [
                    'frequency' => 'weekly',
                    'interval' => 1,
                    'byWeekday' => [1],
                    'ends' => ['type' => 'until', 'date' => '2026-05-31'],
                ],
                'set_by_user_id' => $actorId,
                'last_edited_by_user_id' => $actorId,
            ],
        );

        $roots = OrganizationalUnit::query()
            ->where('organization_id', $organizationId)
            ->whereNull('parent_id', 'and', false)
            ->whereIn('code', ['PAN', 'TAG'], 'and', false)
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        if ($roots->isEmpty()) {
            return;
        }

        $allActiveUnits = OrganizationalUnit::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get(['id', 'parent_id', 'code', 'name']);

        /** @var array<int, list<OrganizationalUnit>> $childrenByParentId */
        $childrenByParentId = [];
        foreach ($allActiveUnits as $unit) {
            $parentId = $unit->parent_id !== null ? (int) $unit->parent_id : 0;
            if (! isset($childrenByParentId[$parentId])) {
                $childrenByParentId[$parentId] = [];
            }
            $childrenByParentId[$parentId][] = $unit;
        }

        foreach ($roots as $root) {
            $rootId = (int) $root->id;
            $queue = [$rootId];
            /** @var list<int> $subtreeIds */
            $subtreeIds = [];

            while ($queue !== []) {
                $current = array_shift($queue);
                if (! is_int($current) || in_array($current, $subtreeIds, true)) {
                    continue;
                }

                $subtreeIds[] = $current;

                foreach ($childrenByParentId[$current] ?? [] as $child) {
                    $queue[] = (int) $child->id;
                }
            }

            sort($subtreeIds);

            foreach ($subtreeIds as $index => $unitId) {
                $unit = $allActiveUnits->firstWhere('id', $unitId);
                if (! $unit instanceof OrganizationalUnit) {
                    continue;
                }

                $startsAt = CarbonImmutable::create(2026, 5, 1, 9, 0, 0)->addDays($index);
                $recurrence = match ($index % 4) {
                    0 => null,
                    1 => [
                        'frequency' => 'weekly',
                        'interval' => 1,
                        'byWeekday' => [2, 4],
                        'ends' => ['type' => 'until', 'date' => '2026-05-31'],
                    ],
                    2 => [
                        'frequency' => 'monthly',
                        'interval' => 1,
                        'ends' => ['type' => 'count', 'count' => 3],
                    ],
                    default => [
                        'frequency' => 'daily',
                        'interval' => 2,
                        'ends' => ['type' => 'count', 'count' => 5],
                    ],
                };

                TeamCalendarEvent::query()->updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'root_unit_id' => $rootId,
                        'unit_id' => $unitId,
                        'title' => sprintf('%s Unit Team Calendar Event', (string) $unit->name),
                        'starts_at' => $startsAt,
                    ],
                    [
                        'category_id' => $categoryIds['meeting'] ?? $categoryIds['planning'],
                        'ends_at' => $startsAt->addMinutes(90),
                        'is_all_day' => false,
                        'location' => sprintf('%s Workspace', (string) $unit->code),
                        'details' => sprintf(
                            'Seeded unit event for %s under %s branch scope.',
                            (string) $unit->name,
                            (string) $root->name,
                        ),
                        'recurrence' => $recurrence,
                        'set_by_user_id' => $actorId,
                        'last_edited_by_user_id' => $actorId,
                    ],
                );
            }
        }
    }
}
