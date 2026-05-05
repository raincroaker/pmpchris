<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\WorkScheduleTemplate;
use Illuminate\Database\Seeder;

class DevelopmentEmployeeAttendanceProfileSeeder extends Seeder
{
    /**
     * Assign biometric attendance IDs and/or work schedule templates to EMP-SEED-* employees for local demos.
     *
     * Runs after {@see WorkScheduleTemplatesSeeder}. Intentionally leaves EMP-SEED-012 without a full
     * profile so Team Attendance / Employee Schedules “complete setup first” flows stay testable.
     */
    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        /** @var array<string, int> $templateIdByName */
        $templateIdByName = WorkScheduleTemplate::query()
            ->where('organization_id', $organization->id)
            ->whereIn('name', [
                WorkScheduleTemplatesSeeder::TEMPLATE_NAME_WEEKDAY_SPLIT_OT,
                WorkScheduleTemplatesSeeder::TEMPLATE_NAME_MON_SAT_SPLIT_OT,
            ])
            ->pluck('id', 'name')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $weekday = WorkScheduleTemplatesSeeder::TEMPLATE_NAME_WEEKDAY_SPLIT_OT;
        $monSat = WorkScheduleTemplatesSeeder::TEMPLATE_NAME_MON_SAT_SPLIT_OT;

        $bothFilled = [
            ['id_number' => 'EMP-SEED-002', 'attendance_id' => 'BIO-HR001', 'template' => $weekday],
            ['id_number' => 'EMP-SEED-003', 'attendance_id' => 'BIO-HRM01', 'template' => $weekday],
            ['id_number' => 'EMP-SEED-005', 'attendance_id' => 'BIO-SYS01', 'template' => $monSat],
            ['id_number' => 'EMP-SEED-007', 'attendance_id' => 'BIO-CEO01', 'template' => $weekday],
            ['id_number' => 'EMP-SEED-008', 'attendance_id' => 'BIO-EMP01', 'template' => $weekday],
            ['id_number' => 'EMP-SEED-011', 'attendance_id' => 'BIO-EMP04', 'template' => $weekday],
            ['id_number' => 'EMP-SEED-014', 'attendance_id' => 'BIO-EMP07', 'template' => $weekday],
        ];

        foreach ($bothFilled as $row) {
            $templateId = $templateIdByName[$row['template']] ?? null;
            if ($templateId === null) {
                continue;
            }

            Employee::query()
                ->where('id_number', $row['id_number'])
                ->update([
                    'attendance_id' => $row['attendance_id'],
                    'work_schedule_template_id' => $templateId,
                ]);
        }

        $weekdayTemplateId = $templateIdByName[$weekday] ?? null;

        if ($weekdayTemplateId !== null) {
            Employee::query()
                ->where('id_number', 'EMP-SEED-010')
                ->update([
                    'attendance_id' => null,
                    'work_schedule_template_id' => $weekdayTemplateId,
                ]);
        }

        Employee::query()
            ->where('id_number', 'EMP-SEED-009')
            ->update([
                'attendance_id' => 'BIO-NOSCHED',
                'work_schedule_template_id' => null,
            ]);

        if ($weekdayTemplateId !== null) {
            $officeProfileRows = [
                ['id_number' => 'EMP-SEED-001', 'attendance_id' => 'BIO-EMP001A'],
                ['id_number' => 'EMP-SEED-004', 'attendance_id' => 'BIO-EMP004A'],
                ['id_number' => 'EMP-SEED-006', 'attendance_id' => 'BIO-EMP006A'],
                ['id_number' => 'EMP-SEED-013', 'attendance_id' => 'BIO-EMP013A'],
            ];

            foreach ($officeProfileRows as $row) {
                Employee::query()
                    ->where('id_number', $row['id_number'])
                    ->update([
                        'attendance_id' => $row['attendance_id'],
                        'work_schedule_template_id' => $weekdayTemplateId,
                    ]);
            }
        }
    }
}
