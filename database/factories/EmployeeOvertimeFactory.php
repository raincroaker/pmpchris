<?php

namespace Database\Factories;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\Employee;
use App\Models\EmployeeOvertime;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeOvertime>
 */
class EmployeeOvertimeFactory extends Factory
{
    protected $model = EmployeeOvertime::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'organizational_unit_id' => null,
            'overtime_policy_id' => function (array $attributes) {
                return OvertimePolicy::factory()->create([
                    'organization_id' => $attributes['organization_id'],
                ])->id;
            },
            'ot_date' => fake()->dateTimeBetween('-1 month', '+2 weeks')->format('Y-m-d'),
            'hours' => fake()->randomFloat(2, 1, 12),
            'status' => fake()->randomElement(EmployeeHrRecordStatus::cases()),
            'submitted_at' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'decided_at' => ($d = fake()->optional(0.85)->dateTimeBetween('-2 months', 'now'))
                ? $d->format('Y-m-d')
                : null,
            'approver_employee_id' => Employee::factory(),
            'reason' => fake()->optional(0.5)->sentence(),
        ];
    }
}
