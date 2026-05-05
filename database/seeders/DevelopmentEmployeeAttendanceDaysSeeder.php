<?php

namespace Database\Seeders;

use App\Enums\AttendanceEntrySource;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use App\Models\Organization;
use Database\Seeders\Concerns\BuildsAttendanceDayRowsFromWorkScheduleTemplates;
use Illuminate\Database\Seeder;

class DevelopmentEmployeeAttendanceDaysSeeder extends Seeder
{
    use BuildsAttendanceDayRowsFromWorkScheduleTemplates;

    /**
     * One {@see EmployeeAttendanceDay} per demo EMP-SEED-* employee who has **both** a biometric
     * {@see Employee::$attendance_id} and an assigned {@see Employee::$work_schedule_template_id}.
     * Segments mirror that template only.
     *
     * Skips employees missing attendance ID, missing template, or incomplete setup personas
     * (e.g. EMP-SEED-009 missing schedule; EMP-SEED-010 missing attendance ID; EMP-SEED-012 untouched).
     *
     * Demos **complete** attendance only: varied {@see EmployeeAttendanceDay::$punctuality} (several **late**),
     * one extended **Overtime** clock-out, plus one **Mon–Sat** complete profile with punch variance — no incomplete or ongoing statuses.
     *
     * Runs after {@see DevelopmentEmployeeAttendanceProfileSeeder}.
     */
    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $orgId = (int) $organization->id;

        $workDate = '2026-05-15';

        $employees = Employee::query()
            ->where('id_number', 'like', 'EMP-SEED-%')
            ->whereNull('deleted_at')
            ->orderBy('id_number')
            ->get();

        foreach ($employees as $employee) {
            if (EmployeeAttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->exists()) {
                continue;
            }

            if (! $this->attendanceRecordingPrerequisitesSatisfied($employee)) {
                continue;
            }

            $template = $this->resolveEmployeeWorkScheduleTemplate($employee);
            if ($template === null) {
                continue;
            }

            $ingestKey = $this->buildDeviceAttendanceIngestKey($workDate, (int) $employee->id);

            $segmentRows = $this->buildSegmentRowsForSeedEmployee(
                (string) $employee->id_number,
                $template,
            );

            $derived = $this->deriveAttendanceDerivedFieldsFromRows($segmentRows, $template);

            $day = EmployeeAttendanceDay::query()->create([
                'organization_id' => $orgId,
                'employee_id' => $employee->id,
                'organizational_unit_id' => null,
                'work_date' => $workDate,
                'work_schedule_template_id' => $template->id,
                'clock_pattern' => $template->clock_pattern,
                'is_overnight_schedule' => $template->is_overnight,
                'ingest_key' => $ingestKey,
                'original_entry_source' => AttendanceEntrySource::Device,
                'last_modified_source' => AttendanceEntrySource::Device,
                'status' => $derived['status'],
                'punctuality' => $derived['punctuality'],
                'net_hours' => $derived['net_hours'],
                'variance_label' => null,
            ]);

            foreach ($segmentRows as $index => $row) {
                EmployeeAttendanceSegment::query()->create([
                    'employee_attendance_day_id' => $day->id,
                    'segment_index' => $index,
                    'label' => $row['label'],
                    'scheduled_in' => $row['scheduled_in'],
                    'scheduled_out' => $row['scheduled_out'],
                    'actual_in' => $row['actual_in'],
                    'actual_out' => $row['actual_out'],
                ]);
            }
        }
    }
}
