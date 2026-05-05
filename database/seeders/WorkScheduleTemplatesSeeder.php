<?php

namespace Database\Seeders;

use App\Enums\WorkScheduleClockPattern;
use App\Models\Organization;
use App\Models\WorkScheduleTemplate;
use Illuminate\Database\Seeder;

class WorkScheduleTemplatesSeeder extends Seeder
{
    public const TEMPLATE_NAME_WEEKDAY_SPLIT_OT = 'Weekday 8-5 split + OT';

    public const TEMPLATE_NAME_MON_SAT_SPLIT_OT = 'Mon-Sat 8-5 split + OT';

    /**
     * Demo work schedules: weekday Mon–Fri and Mon–Sat, each using split sessions with a dedicated OT block.
     */
    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $sessions = [
            ['label' => 'Session 1', 'time_in' => '08:00', 'time_out' => '12:00', 'is_overnight' => false],
            ['label' => 'Session 2', 'time_in' => '13:00', 'time_out' => '17:00', 'is_overnight' => false],
            ['label' => 'Overtime', 'time_in' => '17:00', 'time_out' => '19:00', 'is_overnight' => false],
        ];

        $rows = [
            [
                'name' => self::TEMPLATE_NAME_WEEKDAY_SPLIT_OT,
                'clock_pattern' => WorkScheduleClockPattern::SplitSessions,
                'segments' => $sessions,
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'time_in' => '08:00',
                'time_out' => '19:00',
                'is_overnight' => false,
                'is_active' => true,
                'unpaid_break_minutes' => 60,
                'grace_late_arrival_minutes' => 15,
                'notes' => 'Two regular sessions 08:00–17:00 (lunch gap unpaid); OT as a third session.',
            ],
            [
                'name' => self::TEMPLATE_NAME_MON_SAT_SPLIT_OT,
                'clock_pattern' => WorkScheduleClockPattern::SplitSessions,
                'segments' => $sessions,
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'time_in' => '08:00',
                'time_out' => '19:00',
                'is_overnight' => false,
                'is_active' => true,
                'unpaid_break_minutes' => 60,
                'grace_late_arrival_minutes' => 12,
                'notes' => 'Same session layout as weekday template; Saturday included.',
            ],
        ];

        foreach ($rows as $row) {
            WorkScheduleTemplate::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $row['name'],
                ],
                array_merge($row, [
                    'organization_id' => $organization->id,
                ]),
            );
        }
    }
}
