<?php

namespace Database\Factories;

use App\Enums\LeavePolicyAccrualCadence;
use App\Enums\LeavePolicyUnit;
use App\Models\LeavePolicy;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeavePolicy>
 */
class LeavePolicyFactory extends Factory
{
    protected $model = LeavePolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $useAccrual = fake()->boolean(60);

        return [
            'organization_id' => Organization::factory(),
            'code' => strtoupper(fake()->unique()->lexify('LP???')),
            'name' => fake()->words(3, true),
            'unit' => fake()->randomElement(LeavePolicyUnit::cases()),
            'annual_entitlement' => fake()->randomFloat(2, 5, 30),
            'use_accrual' => $useAccrual,
            'accrual_cadence' => $useAccrual ? fake()->randomElement(LeavePolicyAccrualCadence::cases()) : null,
            'accrual_per_period' => $useAccrual ? fake()->randomFloat(2, 0.5, 2) : null,
            'max_balance' => fake()->optional(0.7)->randomFloat(2, 20, 120),
            'carryover_allowed' => fake()->boolean(70),
            'carryover_cap' => fake()->optional(0.4)->randomFloat(2, 5, 60),
            'paid' => fake()->boolean(85),
            'requires_approval' => fake()->boolean(75),
            'applies_after_months' => fake()->optional(0.2)->numberBetween(0, 12),
            'is_active' => true,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function withoutAccrual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'use_accrual' => false,
            'accrual_cadence' => null,
            'accrual_per_period' => null,
        ]);
    }
}
