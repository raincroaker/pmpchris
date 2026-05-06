<?php

namespace Database\Seeders;

use App\Models\CalendarEventCategory;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Calendar event category catalog only (no company / branch / team events).
 */
class CalendarEventCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()
            ->where('code', 'PMPC')
            ->where('is_active', true)
            ->first();

        if (! $organization instanceof Organization) {
            return;
        }

        $actorId = OrganizationSeedActorResolver::resolveUserId();

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

        $organizationId = (int) $organization->id;

        foreach ($rows as $row) {
            $slug = Str::slug($row['name']);

            CalendarEventCategory::query()->updateOrCreate(
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
        }
    }
}
