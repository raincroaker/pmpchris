<?php

namespace Database\Factories;

use App\Enums\AttendanceEntrySource;
use App\Enums\AttendanceRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\Organization;
use App\Models\WorkScheduleTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAttendanceDay>
 */
class EmployeeAttendanceDayFactory extends Factory
{
    protected $model = EmployeeAttendanceDay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'organizational_unit_id' => null,
            'work_date' => fake()->date(),
            'work_schedule_template_id' => function (array $attributes) {
                return WorkScheduleTemplate::factory()->create([
                    'organization_id' => $attributes['organization_id'],
                ])->id;
            },
            'clock_pattern' => WorkScheduleClockPattern::SinglePair,
            'is_overnight_schedule' => false,
            'ingest_key' => null,
            'original_entry_source' => AttendanceEntrySource::Manual,
            'last_modified_source' => AttendanceEntrySource::Manual,
            'status' => AttendanceRecordStatus::Complete,
            'punctuality' => null,
            'net_hours' => 8,
            'variance_label' => null,
        ];
    }
}
