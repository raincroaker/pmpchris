<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeContact>
 */
class EmployeeContactFactory extends Factory
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
            'category' => 'personal',
            'type' => fake()->randomElement(['mobile', 'home', 'work']),
            'contact_person' => null,
            'relationship' => null,
            'contact_number' => fake()->numerify('+639#########'),
            'email' => fake()->optional(0.4)->safeEmail(),
            'is_primary' => false,
        ];
    }

    /**
     * Emergency contact with person and relationship; type may be mobile or null.
     */
    public function emergency(): static
    {
        return $this->state(fn () => [
            'category' => 'emergency',
            'type' => fake()->boolean(60) ? 'mobile' : null,
            'contact_person' => fake()->name(),
            'relationship' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Other']),
            'contact_number' => fake()->numerify('+639#########'),
            'email' => fake()->optional(0.2)->safeEmail(),
        ]);
    }
}
