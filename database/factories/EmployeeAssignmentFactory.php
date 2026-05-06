<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAssignment>
 */
class EmployeeAssignmentFactory extends Factory
{
    /**
     * Default: unit-level assignment (organizational_unit_id set, organization_id null) per employee_assignments XOR check.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'employee_employment_id' => function (array $attributes): int {
                return EmployeeEmployment::factory()->create([
                    'employee_id' => $attributes['employee_id'],
                ])->id;
            },
            'organization_id' => null,
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'is_primary' => false,
            'is_head' => false,
            'start_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'end_date' => null,
        ];
    }
}
