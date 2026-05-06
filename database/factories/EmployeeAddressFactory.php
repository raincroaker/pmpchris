<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAddress>
 */
class EmployeeAddressFactory extends Factory
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
            'type' => fake()->randomElement(['current', 'permanent']),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.25)->secondaryAddress(),
            'barangay' => 'Barangay '.fake()->lastName(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'zip_code' => fake()->numerify('####'),
            'country' => 'Philippines',
            'is_primary' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => [
            'type' => 'current',
        ]);
    }

    public function permanent(): static
    {
        return $this->state(fn () => [
            'type' => 'permanent',
        ]);
    }
}
