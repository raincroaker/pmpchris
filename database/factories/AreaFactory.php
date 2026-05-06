<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('AR????')),
            'name' => fake()->city().' Area',
            'organization_id' => Organization::factory(),
            'is_active' => true,
        ];
    }
}
