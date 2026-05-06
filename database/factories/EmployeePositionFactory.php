<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePosition>
 */
class EmployeePositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'employee_employment_id' => function (array $attributes): int {
                $employeeId = (int) $attributes['employee_id'];

                $employment = EmployeeEmployment::query()
                    ->where('employee_id', $employeeId)
                    ->orderByDesc('is_current')
                    ->orderByDesc('hire_date')
                    ->orderByDesc('id')
                    ->first();

                if ($employment instanceof EmployeeEmployment) {
                    return (int) $employment->id;
                }

                return EmployeeEmployment::factory()->create([
                    'employee_id' => $employeeId,
                ])->id;
            },
            'position_id' => Position::factory(),
            'is_primary' => false,
            'start_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'end_date' => null,
            'notes' => fake()->optional(0.25)->sentence(),
        ];
    }
}
