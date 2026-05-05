<?php

namespace Database\Factories;

use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAttendanceSegment>
 */
class EmployeeAttendanceSegmentFactory extends Factory
{
    protected $model = EmployeeAttendanceSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_attendance_day_id' => EmployeeAttendanceDay::factory(),
            'segment_index' => 0,
            'label' => 'Session 1',
            'scheduled_in' => '09:00',
            'scheduled_out' => '17:00',
            'actual_in' => '08:58',
            'actual_out' => '17:02',
        ];
    }
}
