<?php

namespace Database\Factories;

use App\Enums\WorkScheduleClockPattern;
use App\Models\Organization;
use App\Models\WorkScheduleTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkScheduleTemplate>
 */
class WorkScheduleTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true),
            'clock_pattern' => WorkScheduleClockPattern::SinglePair,
            'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'segments' => null,
            'time_in' => '09:00',
            'time_out' => '17:00',
            'is_overnight' => false,
            'is_active' => true,
            'unpaid_break_minutes' => 60,
            'grace_late_arrival_minutes' => 10,
            'notes' => null,
            'attendance_rules' => null,
            'overtime_rules' => null,
        ];
    }
}
