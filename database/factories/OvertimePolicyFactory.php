<?php

namespace Database\Factories;

use App\Enums\OvertimePolicyContext;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimePolicy>
 */
class OvertimePolicyFactory extends Factory
{
    protected $model = OvertimePolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->unique()->lexify('OT???')),
            'name' => fake()->words(3, true),
            'context' => fake()->randomElement(OvertimePolicyContext::cases()),
            'rate_multiplier' => fake()->randomElement([1.25, 1.3, 1.5, 2.0]),
            'daily_threshold_hours' => fake()->randomElement([0, 8]),
            'daily_cap_hours' => fake()->optional(0.5)->randomFloat(2, 2, 8),
            'weekly_cap_hours' => fake()->optional(0.5)->randomFloat(2, 12, 40),
            'requires_approval' => fake()->boolean(80),
            'minimum_lead_time_hours' => fake()->optional(0.4)->randomFloat(2, 8, 72),
            'is_active' => true,
            'notes' => fake()->optional(0.25)->sentence(),
        ];
    }
}
