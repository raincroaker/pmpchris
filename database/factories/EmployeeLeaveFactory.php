<?php

namespace Database\Factories;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeavePolicy;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeLeave>
 */
class EmployeeLeaveFactory extends Factory
{
    protected $model = EmployeeLeave::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 week');
        $end = (clone $start)->modify('+'.fake()->numberBetween(0, 5).' days');

        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'organizational_unit_id' => null,
            'leave_policy_id' => function (array $attributes) {
                return LeavePolicy::factory()->create([
                    'organization_id' => $attributes['organization_id'],
                ])->id;
            },
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'status' => fake()->randomElement(EmployeeHrRecordStatus::cases()),
            'submitted_at' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'decided_at' => ($d = fake()->optional(0.85)->dateTimeBetween('-2 months', 'now'))
                ? $d->format('Y-m-d')
                : null,
            'approver_employee_id' => Employee::factory(),
            'reason' => fake()->optional(0.6)->sentence(),
        ];
    }
}
