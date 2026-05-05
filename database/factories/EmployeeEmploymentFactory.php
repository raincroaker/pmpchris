<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeEmployment>
 */
class EmployeeEmploymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hireDate = fake()->dateTimeBetween('-10 years', '-6 months');

        return [
            'employee_id' => Employee::factory(),
            'hire_date' => $hireDate->format('Y-m-d'),
            'separation_date' => null,
            'separation_reason' => null,
            'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
            'is_current' => true,
            'notes' => fake()->optional(0.35)->sentence(),
        ];
    }

    public function inactive(): self
    {
        return $this->state(function (array $attributes): array {
            $hireDate = fake()->dateTimeBetween('-10 years', '-2 years');
            $separationDate = fake()->dateTimeBetween($hireDate, 'now');

            return [
                'hire_date' => $hireDate->format('Y-m-d'),
                'separation_date' => $separationDate->format('Y-m-d'),
                'separation_reason' => 'Inactive record',
                'employment_status' => EmployeeEmployment::STATUS_INACTIVE,
                'is_current' => false,
            ];
        });
    }

    public function resigned(): self
    {
        return $this->state(function (array $attributes): array {
            $hireDate = fake()->dateTimeBetween('-10 years', '-2 years');
            $separationDate = fake()->dateTimeBetween($hireDate, 'now');

            return [
                'hire_date' => $hireDate->format('Y-m-d'),
                'separation_date' => $separationDate->format('Y-m-d'),
                'separation_reason' => 'Resigned',
                'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
                'is_current' => false,
            ];
        });
    }

    public function terminated(): self
    {
        return $this->state(function (array $attributes): array {
            $hireDate = fake()->dateTimeBetween('-10 years', '-2 years');
            $separationDate = fake()->dateTimeBetween($hireDate, 'now');

            return [
                'hire_date' => $hireDate->format('Y-m-d'),
                'separation_date' => $separationDate->format('Y-m-d'),
                'separation_reason' => 'Terminated',
                'employment_status' => EmployeeEmployment::STATUS_TERMINATED,
                'is_current' => false,
            ];
        });
    }

    public function retired(): self
    {
        return $this->state(function (array $attributes): array {
            $hireDate = fake()->dateTimeBetween('-25 years', '-10 years');
            $separationDate = fake()->dateTimeBetween($hireDate, 'now');

            return [
                'hire_date' => $hireDate->format('Y-m-d'),
                'separation_date' => $separationDate->format('Y-m-d'),
                'separation_reason' => 'Retired',
                'employment_status' => EmployeeEmployment::STATUS_RETIRED,
                'is_current' => false,
            ];
        });
    }

    public function contractEnded(): self
    {
        return $this->state(function (array $attributes): array {
            $hireDate = fake()->dateTimeBetween('-10 years', '-2 years');
            $separationDate = fake()->dateTimeBetween($hireDate, 'now');

            return [
                'hire_date' => $hireDate->format('Y-m-d'),
                'separation_date' => $separationDate->format('Y-m-d'),
                'separation_reason' => 'Contract ended',
                'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
                'is_current' => false,
            ];
        });
    }
}
