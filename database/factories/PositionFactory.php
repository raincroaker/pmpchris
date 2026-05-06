<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('POS?????')),
            'title' => fake()->jobTitle(),
            'description' => fake()->optional(0.3)->sentence(),
            'organization_id' => Organization::factory(),
            'is_active' => true,
        ];
    }

    /**
     * @return $this
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
