<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitType>
 */
class UnitTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'color' => '#'.fake()->regexify('[0-9A-F]{6}'),
            'can_be_root' => false,
            'description' => null,
            'is_active' => true,
        ];
    }
}
