<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Seeder;

class DevelopmentEmployeeAffiliationSeeder extends Seeder
{
    /**
     * Lean EMP-SEED-* affiliation matrix under PMPC.
     * - PAN + TAG roots only.
     * - super_admin, hr_head, and both hr_managers are affiliated to both branches.
     * - One Talent Acquisition employee is dual-affiliated for cross-branch assignment to Employee Relations.
     */
    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $orgId = (int) $organization->id;

        $employees = Employee::query()
            ->where('id_number', 'like', 'EMP-SEED-%')
            ->orderBy('id_number')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $employeeIds = $employees->pluck('id');

        EmployeeAffiliation::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('organization_id', $orgId)
            ->forceDelete();

        $rootCodes = ['PAN', 'TAG'];
        /** @var array<string, int> $rootIdsByCode */
        $rootIdsByCode = OrganizationalUnit::query()
            ->where('organization_id', $orgId)
            ->whereNull('parent_id')
            ->whereIn('code', $rootCodes)
            ->pluck('id', 'code')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($rootCodes as $requiredCode) {
            if (! isset($rootIdsByCode[$requiredCode])) {
                throw new \RuntimeException("Demo PMPC branch root {$requiredCode} is required before DevelopmentEmployeeAffiliationSeeder.");
            }
        }

        /** @var array<string, list<string>> $branchAffiliationsByEmployee */
        $branchAffiliationsByEmployee = [
            'EMP-SEED-001' => ['PAN', 'TAG'], // super admin
            'EMP-SEED-002' => ['PAN', 'TAG'], // hr head
            'EMP-SEED-003' => ['PAN', 'TAG'], // hr manager 1
            'EMP-SEED-004' => ['TAG', 'PAN'], // hr manager 2
            'EMP-SEED-005' => ['PAN'],
            'EMP-SEED-006' => ['PAN'],
            'EMP-SEED-007' => ['PAN'],
            'EMP-SEED-008' => ['PAN'],
            'EMP-SEED-009' => ['PAN'],
            'EMP-SEED-010' => ['PAN'],
            'EMP-SEED-011' => ['PAN'],
            'EMP-SEED-012' => ['PAN'],
            'EMP-SEED-013' => ['PAN'],
            'EMP-SEED-014' => ['PAN'],
            'EMP-SEED-015' => ['PAN'], // IT
            'EMP-SEED-016' => ['PAN'], // IT
            'EMP-SEED-017' => ['PAN', 'TAG'], // TA cross-branch
            'EMP-SEED-018' => ['PAN'], // TA
            'EMP-SEED-019' => ['TAG'], // Employee Relations
            'EMP-SEED-020' => ['PAN'], // Intern (no account)
            'EMP-SEED-021' => ['PAN'], // Intern (no account)
        ];

        foreach ($employees as $employee) {
            $idNumber = (string) $employee->id_number;

            if (! isset($branchAffiliationsByEmployee[$idNumber])) {
                continue;
            }

            $employmentId = $this->resolveCurrentEmploymentId((int) $employee->id);
            /** @var EmployeeEmployment|null $employment */
            $employment = EmployeeEmployment::query()->find($employmentId);
            $affiliationEndDate = $employment !== null && ! $employment->is_current
                ? $employment->separation_date
                : null;
            foreach ($branchAffiliationsByEmployee[$idNumber] as $index => $rootCode) {
                $this->createAffiliation(
                    (int) $employee->id,
                    $orgId,
                    $rootIdsByCode[$rootCode] ?? null,
                    $index === 0,
                    $employmentId,
                    $affiliationEndDate,
                );
            }
        }
    }

    private function createAffiliation(
        int $employeeId,
        int $organizationId,
        ?int $rootUnitId,
        bool $isPrimary,
        int $employmentId,
        ?string $endDate,
    ): void {
        EmployeeAffiliation::query()->create([
            'employee_id' => $employeeId,
            'employee_employment_id' => $employmentId,
            'organization_id' => $organizationId,
            'root_unit_id' => $rootUnitId,
            'is_primary' => $isPrimary,
            'start_date' => '2010-01-01',
            'end_date' => $endDate,
        ]);
    }

    private function resolveCurrentEmploymentId(int $employeeId): int
    {
        /** @var EmployeeEmployment $employment */
        $employment = EmployeeEmployment::query()
            ->where('employee_id', $employeeId)
            ->where('is_current', true)
            ->latest('hire_date')
            ->first();

        if ($employment === null) {
            $employment = EmployeeEmployment::query()
                ->where('employee_id', $employeeId)
                ->latest('hire_date')
                ->first();
        }

        if ($employment === null) {
            $employment = EmployeeEmployment::query()->create([
                'employee_id' => $employeeId,
                'hire_date' => '2020-01-01',
                'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
                'is_current' => true,
                'separation_date' => null,
                'separation_reason' => null,
                'notes' => 'Auto-created fallback by development affiliation seeder.',
            ]);
        }

        return (int) $employment->id;
    }
}
