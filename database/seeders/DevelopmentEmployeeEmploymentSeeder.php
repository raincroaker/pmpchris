<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use Illuminate\Database\Seeder;

class DevelopmentEmployeeEmploymentSeeder extends Seeder
{
    /**
     * Seed deterministic employment history for local EMP-SEED-* records.
     */
    public function run(): void
    {
        $employees = Employee::query()
            ->where('id_number', 'like', 'EMP-SEED-%')
            ->orderBy('id_number')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        foreach ($employees as $employee) {
            foreach ($this->employmentRowsForEmployee((string) $employee->id_number) as $row) {
                /** @var EmployeeEmployment|null $existing */
                $existing = EmployeeEmployment::query()
                    ->where('employee_id', (int) $employee->id)
                    ->whereDate('hire_date', $row['hire_date'])
                    ->first();

                if ($existing !== null) {
                    $existing->update([
                        'separation_date' => $row['separation_date'],
                        'separation_reason' => $row['separation_reason'],
                        'employment_status' => $row['employment_status'],
                        'is_current' => $row['is_current'],
                        'notes' => $row['notes'],
                    ]);

                    continue;
                }

                EmployeeEmployment::query()->create([
                    'employee_id' => (int) $employee->id,
                    'hire_date' => $row['hire_date'],
                    'separation_date' => $row['separation_date'],
                    'separation_reason' => $row['separation_reason'],
                    'employment_status' => $row['employment_status'],
                    'is_current' => $row['is_current'],
                    'notes' => $row['notes'],
                ]);
            }
        }
    }

    /**
     * @return list<array{
     *     hire_date: string,
     *     separation_date: string|null,
     *     separation_reason: string|null,
     *     employment_status: string,
     *     is_current: bool,
     *     notes: string
     * }>
     */
    private function employmentRowsForEmployee(string $idNumber): array
    {
        return match ($idNumber) {
            'EMP-SEED-007' => [[
                'hire_date' => '1995-01-01',
                'separation_date' => '2024-12-31',
                'separation_reason' => 'Retired after long service.',
                'employment_status' => EmployeeEmployment::STATUS_RETIRED,
                'is_current' => false,
                'notes' => 'Legacy executive sample retired record.',
            ]],
            'EMP-SEED-010' => [[
                'hire_date' => '2020-01-01',
                'separation_date' => '2025-04-30',
                'separation_reason' => 'Contract ended after internship completion.',
                'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
                'is_current' => false,
                'notes' => 'Intern contract-ended sample.',
            ]],
            'EMP-SEED-011' => [[
                'hire_date' => '2020-01-01',
                'separation_date' => '2025-03-31',
                'separation_reason' => 'Contract ended after internship completion.',
                'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
                'is_current' => false,
                'notes' => 'Intern contract-ended sample.',
            ]],
            'EMP-SEED-012' => [[
                'hire_date' => '2016-06-01',
                'separation_date' => '2025-02-15',
                'separation_reason' => 'Resigned for a new opportunity.',
                'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
                'is_current' => false,
                'notes' => 'Resigned employee sample.',
            ]],
            'EMP-SEED-013' => [[
                'hire_date' => '2018-09-17',
                'separation_date' => '2025-01-10',
                'separation_reason' => 'Terminated due to policy violation.',
                'employment_status' => EmployeeEmployment::STATUS_TERMINATED,
                'is_current' => false,
                'notes' => 'Terminated employee sample.',
            ]],
            'EMP-SEED-014' => [[
                'hire_date' => '2020-04-01',
                'separation_date' => '2022-11-30',
                'separation_reason' => 'Contract ended.',
                'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
                'is_current' => false,
                'notes' => 'Contract-ended employee sample (not rehired).',
            ]],
            'EMP-SEED-019' => [[
                'hire_date' => '2020-01-01',
                'separation_date' => '2025-02-28',
                'separation_reason' => 'Resigned for a new opportunity.',
                'employment_status' => EmployeeEmployment::STATUS_RESIGNED,
                'is_current' => false,
                'notes' => 'Separated employee sample in current org structure.',
            ]],
            'EMP-SEED-020' => [[
                'hire_date' => '2024-01-15',
                'separation_date' => null,
                'separation_reason' => null,
                'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
                'is_current' => true,
                'notes' => 'Intern sample without account.',
            ]],
            'EMP-SEED-021' => [[
                'hire_date' => '2024-02-01',
                'separation_date' => null,
                'separation_reason' => null,
                'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
                'is_current' => true,
                'notes' => 'Intern sample without account.',
            ]],
            default => [[
                'hire_date' => '2020-01-01',
                'separation_date' => null,
                'separation_reason' => null,
                'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
                'is_current' => true,
                'notes' => 'Default active development employment record.',
            ]],
        };
    }
}
