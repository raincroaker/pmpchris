<?php

namespace Database\Factories;

use App\Models\BranchManager;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchManager>
 */
class BranchManagerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'root_unit_id' => OrganizationalUnit::factory(),
            'assigned_by_user_id' => null,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'notes' => fake()->optional(0.25)->sentence(),
        ];
    }
}
