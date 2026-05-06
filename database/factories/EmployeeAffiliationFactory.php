<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EmployeeAffiliation>
 */
class EmployeeAffiliationFactory extends Factory
{
    /**
     * Default: root unit with can_be_root type; {@see EmployeeAffiliation} sets organization_id from that unit on save.
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
            'root_unit_id' => OrganizationalUnit::factory()->state([
                'unit_type_id' => UnitType::factory()->state([
                    'can_be_root' => true,
                ]),
            ]),
            'is_primary' => false,
            'start_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'end_date' => null,
        ];
    }
}
