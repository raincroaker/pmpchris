<?php

namespace Database\Factories;

use App\Models\EmployeeAssignment;
use App\Models\EmployeePosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssignmentPosition>
 */
class AssignmentPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_assignment_id' => EmployeeAssignment::factory(),
            'employee_position_id' => EmployeePosition::factory(),
            'is_primary_for_assignment' => false,
            'start_date' => fake()->dateTimeBetween('-2 years', '-1 months')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('-1 months', '+1 years')->format('Y-m-d'),
        ];
    }
}
