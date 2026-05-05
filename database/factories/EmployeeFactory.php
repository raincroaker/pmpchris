<?php

namespace Database\Factories;

use App\Enums\EmployeeBirthdayVisibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_number' => fake()->unique()->numerify('EMP-######'),
            'attendance_id' => fake()->optional(0.35)->numerify('ATT-######'),
            'work_schedule_template_id' => null,
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.4)->firstName(),
            'last_name' => fake()->lastName(),
            'suffix' => fake()->optional(0.1)->randomElement(['Jr.', 'Sr.', 'III']),
            'birthdate' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'birthday_visibility' => fake()->randomElement(EmployeeBirthdayVisibility::cases()),
            'sex' => fake()->randomElement(['male', 'female', 'other']),
            'civil_status' => fake()->randomElement(['single', 'married', 'widowed', 'divorced']),
            'nationality' => fake()->boolean(88)
                ? 'Filipino'
                : fake()->randomElement([
                    'American',
                    'Japanese',
                    'Canadian',
                    'Australian',
                    'Chinese',
                    'British',
                ]),
            'religion' => fake()->optional(0.5)->randomElement(['Christianity', 'Islam', 'Buddhism', 'Hinduism']),
        ];
    }
}
