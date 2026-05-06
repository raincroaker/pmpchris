<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationalUnit>
 */
class OrganizationalUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('OU????')),
            'name' => fake()->words(2, true),
            'unit_type_id' => UnitType::factory(),
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'area_id' => null,
            'address' => null,
            'is_active' => true,
        ];
    }
}
