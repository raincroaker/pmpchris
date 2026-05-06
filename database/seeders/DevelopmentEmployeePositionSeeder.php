<?php

namespace Database\Seeders;

use App\Models\AssignmentPosition;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DevelopmentEmployeePositionSeeder extends Seeder
{
    private string $itHeadIdNumber = 'EMP-SEED-015';

    private string $taHeadIdNumber = 'EMP-SEED-017';

    /**
     * Local-only seed data for employee positions and assignment-position links.
     * EMP-SEED-001 … EMP-SEED-014 use explicit personas (org/branch/department/section assignment variety);
     * other EMP-SEED-* use weighted fallback.
     * Safe to re-run: uses updateOrCreate with stable keys.
     */
    public function run(): void
    {
        $this->itHeadIdNumber = random_int(0, 1) === 0 ? 'EMP-SEED-015' : 'EMP-SEED-016';
        $this->taHeadIdNumber = random_int(0, 1) === 0 ? 'EMP-SEED-017' : 'EMP-SEED-018';

        /** @var Collection<int, Employee> $employees */
        $employees = Employee::query()
            ->where('id_number', 'like', 'EMP-SEED-%')
            ->orderBy('id_number')
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $orgId = (int) $organization->id;

        /** @var Collection<int, Position> $positions */
        $positions = Position::query()
            ->where('is_active', true)
            ->where('organization_id', $orgId)
            ->orderBy('code')
            ->get();

        if ($positions->isEmpty()) {
            return;
        }

        /** @var Collection<string, Position> $positionsByCode */
        $positionsByCode = $positions->keyBy('code');

        /** @var Collection<string, OrganizationalUnit> $unitsByCode */
        $unitsByCode = OrganizationalUnit::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (OrganizationalUnit $u): string => (string) $u->code);

        foreach ($employees as $employee) {
            $this->clearExistingPositionSeedData($employee);
            $roleCodes = $this->roleCodesForEmployee($employee);

            if ($this->hasExplicitDevelopmentPersona($employee->id_number)) {
                $config = $this->explicitDevelopmentPersona($employee->id_number);
                if ($config !== null) {
                    $this->materializePersona($employee, $config, $positionsByCode, $unitsByCode, $orgId);

                    continue;
                }
            }

            $this->seedEmployeeFallback($employee, $positions, $orgId, $unitsByCode, $roleCodes);
        }
    }

    /**
     * @param  Collection<string, Position>  $positionsByCode
     * @param  Collection<string, OrganizationalUnit>  $unitsByCode
     */
    private function materializePersona(
        Employee $employee,
        array $config,
        Collection $positionsByCode,
        Collection $unitsByCode,
        int $organizationId,
    ): void {
        $employmentId = $this->resolveCurrentEmploymentId((int) $employee->id);
        /** @var EmployeeEmployment $employmentRecord */
        $employmentRecord = EmployeeEmployment::query()->findOrFail($employmentId);
        $hire = CarbonImmutable::parse((string) $employmentRecord->hire_date);

        foreach ($config['employee_positions'] as $spec) {
            /** @var Position $position */
            $position = $positionsByCode->get($spec['code']);
            if ($position === null) {
                throw new \RuntimeException("Position code {$spec['code']} missing for development persona {$employee->id_number}.");
            }

            $start = CarbonImmutable::parse($spec['start']);
            if ($start->lt($hire)) {
                $start = $hire;
            }

            /** @var CarbonImmutable|null $end */
            $end = isset($spec['end']) ? CarbonImmutable::parse($spec['end']) : null;
            if ($end !== null && $end->lt($start)) {
                $end = $start;
            }

            $this->upsertEmployeePosition(
                $employee,
                $employmentId,
                $position,
                $start,
                $end,
                $spec['is_primary'],
                $spec['notes'] ?? 'Seeded position',
            );
        }

        foreach ($config['assignments'] as $spec) {
            $assignmentStart = CarbonImmutable::parse($spec['start']);
            if ($assignmentStart->lt($hire)) {
                $assignmentStart = $hire;
            }

            $assignmentEnd = isset($spec['end']) ? CarbonImmutable::parse($spec['end']) : null;
            if ($assignmentEnd !== null && $assignmentEnd->lt($assignmentStart)) {
                $assignmentEnd = $assignmentStart;
            }

            if (! empty($spec['org_level'])) {
                EmployeeAssignment::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'organization_id' => $organizationId,
                        'start_date' => $assignmentStart->toDateString(),
                    ],
                    [
                        'organizational_unit_id' => null,
                        'employee_employment_id' => $employmentId,
                        'end_date' => $assignmentEnd?->toDateString(),
                        'is_primary' => $spec['is_primary'],
                        'is_head' => $spec['is_head'],
                    ]
                );

                continue;
            }

            /** @var OrganizationalUnit|null $unit */
            $unit = $unitsByCode->get($spec['unit_code']);
            if ($unit === null) {
                throw new \RuntimeException("Organizational unit {$spec['unit_code']} missing for development persona {$employee->id_number}.");
            }

            $this->assertEmployeeCanBeAssignedToUnit($employee, $unit, $assignmentStart->toDateString());

            EmployeeAssignment::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'organizational_unit_id' => $unit->id,
                    'start_date' => $assignmentStart->toDateString(),
                ],
                [
                    'organization_id' => null,
                    'employee_employment_id' => $employmentId,
                    'end_date' => $assignmentEnd?->toDateString(),
                    'is_primary' => $spec['is_primary'],
                    'is_head' => $spec['is_head'],
                ]
            );
        }

        $this->syncAssignmentPositions($employee);
    }

    /**
     * @return array{employee_positions: list<array{code: string, start: string, end?: string, is_primary: bool, notes?: string}>, assignments: list<array{unit_code: string, start: string, end?: string, is_primary: bool, is_head: bool}>}|null
     */
    private function explicitDevelopmentPersona(string $idNumber): ?array
    {
        return match ($idNumber) {
            'EMP-SEED-001' => [
                'employee_positions' => [
                    ['code' => 'IT-SUP', 'start' => '2023-02-01', 'is_primary' => true, 'notes' => 'Super admin scope'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN', 'start' => '2023-02-01', 'is_primary' => true, 'is_head' => false],
                    ['unit_code' => 'TAG', 'start' => '2023-02-01', 'is_primary' => false, 'is_head' => false],
                ],
            ],
            'EMP-SEED-002' => [
                'employee_positions' => [
                    ['code' => 'HR-MGR', 'start' => '2019-03-01', 'is_primary' => true, 'notes' => 'Primary HR leadership'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN', 'start' => '2019-03-01', 'is_primary' => true, 'is_head' => true],
                    ['unit_code' => 'TAG', 'start' => '2019-03-01', 'is_primary' => false, 'is_head' => true],
                ],
            ],
            'EMP-SEED-003' => [
                'employee_positions' => [
                    ['code' => 'HR-MGR', 'start' => '2018-05-01', 'is_primary' => true, 'notes' => 'Primary current position'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN', 'start' => '2018-05-01', 'is_primary' => true, 'is_head' => true],
                ],
            ],
            'EMP-SEED-004' => [
                'employee_positions' => [
                    ['code' => 'HR-MGR', 'start' => '2021-01-10', 'is_primary' => true, 'notes' => 'Primary current position'],
                ],
                'assignments' => [
                    ['unit_code' => 'TAG', 'start' => '2021-01-10', 'is_primary' => true, 'is_head' => true],
                ],
            ],
            'EMP-SEED-005' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2022-03-01', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2022-03-01', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-006' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2019-07-01', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2019-07-01', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-007' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2015-01-01', 'end' => '2024-12-31', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2015-01-01', 'end' => '2024-12-31', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-008' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2023-04-01', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2023-04-01', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-009' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2022-11-01', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2022-11-01', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-010' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2022-06-20', 'end' => '2025-04-30', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2022-06-20', 'end' => '2025-04-30', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-011' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2021-01-01', 'end' => '2025-03-31', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2021-01-01', 'end' => '2025-03-31', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-012' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2023-01-20', 'end' => '2025-02-15', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2023-01-20', 'end' => '2025-02-15', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-013' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2022-08-01', 'end' => '2025-01-10', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2022-08-01', 'end' => '2025-01-10', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-014' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2020-04-01', 'end' => '2022-11-30', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2020-04-01', 'end' => '2022-11-30', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-015' => [
                'employee_positions' => [
                    ['code' => 'IT-SUP', 'start' => '2022-09-01', 'is_primary' => true, 'notes' => 'Information Technology'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D1', 'start' => '2022-09-01', 'is_primary' => true, 'is_head' => $this->itHeadIdNumber === 'EMP-SEED-015'],
                ],
            ],
            'EMP-SEED-016' => [
                'employee_positions' => [
                    ['code' => 'IT-SUP', 'start' => '2022-10-01', 'is_primary' => true, 'notes' => 'Information Technology'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D1', 'start' => '2022-10-01', 'is_primary' => true, 'is_head' => $this->itHeadIdNumber === 'EMP-SEED-016'],
                ],
            ],
            'EMP-SEED-017' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2024-01-01', 'is_primary' => true, 'notes' => 'Talent Acquisition'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S2', 'start' => '2024-01-01', 'is_primary' => true, 'is_head' => $this->taHeadIdNumber === 'EMP-SEED-017'],
                    ['unit_code' => 'TAG-D1-S1', 'start' => '2024-02-01', 'is_primary' => false, 'is_head' => false],
                ],
            ],
            'EMP-SEED-018' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2024-01-15', 'is_primary' => true, 'notes' => 'Talent Acquisition'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S2', 'start' => '2024-01-15', 'is_primary' => true, 'is_head' => $this->taHeadIdNumber === 'EMP-SEED-018'],
                ],
            ],
            'EMP-SEED-019' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2024-02-01', 'end' => '2025-02-28', 'is_primary' => true, 'notes' => 'Employee Relations'],
                ],
                'assignments' => [
                    ['unit_code' => 'TAG-D1-S1', 'start' => '2024-02-01', 'end' => '2025-02-28', 'is_primary' => true, 'is_head' => true],
                ],
            ],
            'EMP-SEED-020' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2024-01-15', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2024-01-15', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            'EMP-SEED-021' => [
                'employee_positions' => [
                    ['code' => 'HR-SPEC', 'start' => '2024-02-01', 'is_primary' => true, 'notes' => 'Interns'],
                ],
                'assignments' => [
                    ['unit_code' => 'PAN-D2-S1', 'start' => '2024-02-01', 'is_primary' => true, 'is_head' => false],
                ],
            ],
            default => null,
        };
    }

    private function hasExplicitDevelopmentPersona(string $idNumber): bool
    {
        return preg_match('/^EMP-SEED-(00[1-9]|01[0-9]|02[0-1])$/', $idNumber) === 1;
    }

    /**
     * @param  Collection<int, Position>  $positions
     * @param  Collection<string, OrganizationalUnit>  $unitsByCode
     * @param  list<string>  $roleCodes
     */
    private function seedEmployeeFallback(
        Employee $employee,
        Collection $positions,
        int $organizationId,
        Collection $unitsByCode,
        array $roleCodes,
    ): void {
        $employmentId = $this->resolveCurrentEmploymentId((int) $employee->id);
        /** @var EmployeeEmployment $employmentRecord */
        $employmentRecord = EmployeeEmployment::query()->findOrFail($employmentId);
        $hire = CarbonImmutable::parse((string) $employmentRecord->hire_date);

        $positionsByOrganization = $positions->where('organization_id', $organizationId)->values();
        if ($positionsByOrganization->isEmpty()) {
            return;
        }

        /** @var Collection<int, OrganizationalUnit> $unitsByOrganization */
        $unitsByOrganization = $unitsByCode->values()->sortBy('code')->values();

        if ($unitsByOrganization->isEmpty()) {
            return;
        }

        /** @var Collection<int, OrganizationalUnit> $eligibleUnits */
        $eligibleUnits = $this->eligibleUnitsForEmployee($employee, $unitsByOrganization, $organizationId);
        if ($eligibleUnits->isEmpty()) {
            return;
        }

        $sequence = $this->sequenceFromIdNumber($employee->id_number, $employee->id);
        [$primaryPosition, $secondaryPosition] = $this->pickPositionsForEmployee($positionsByOrganization, $sequence, $roleCodes);
        [$primaryUnit, $secondaryUnit] = $this->pickUnitsForEmployee($eligibleUnits, $sequence);

        $primaryPositionStart = CarbonImmutable::create(2023, 1, 1)->addMonths($sequence);
        if ($primaryPositionStart->lt($hire)) {
            $primaryPositionStart = $hire;
        }

        $primaryEmployeePosition = $this->upsertEmployeePosition(
            $employee,
            $employmentId,
            $primaryPosition,
            $primaryPositionStart,
            null,
            true,
            'Primary current position'
        );

        $secondaryEmployeePosition = null;
        if ($sequence % 3 === 0 && $secondaryPosition !== null) {
            $secondaryStart = CarbonImmutable::create(2021, 1, 1)->addMonths($sequence);
            if ($secondaryStart->lt($hire)) {
                $secondaryStart = $hire;
            }

            $secondaryEnd = CarbonImmutable::create(2022, 12, 31)->addDays($sequence % 27);
            if ($secondaryEnd->gte($secondaryStart)) {
                $secondaryEmployeePosition = $this->upsertEmployeePosition(
                    $employee,
                    $employmentId,
                    $secondaryPosition,
                    $secondaryStart,
                    $secondaryEnd,
                    false,
                    'Historical position'
                );
            }
        }

        $this->upsertAssignmentsAndLinks(
            $employee,
            $sequence,
            $primaryUnit,
            $secondaryEmployeePosition !== null ? $secondaryUnit : null,
            $primaryEmployeePosition,
            $secondaryEmployeePosition,
            $roleCodes,
            $employmentId,
            $hire,
        );
    }

    private function sequenceFromIdNumber(string $idNumber, int $fallbackId): int
    {
        if (preg_match('/EMP-SEED-(\d{3})$/', $idNumber, $matches) === 1) {
            return (int) $matches[1];
        }

        return $fallbackId;
    }

    private function clearExistingPositionSeedData(Employee $employee): void
    {
        $assignmentIds = EmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->pluck('id');

        if ($assignmentIds->isNotEmpty()) {
            AssignmentPosition::query()
                ->whereIn('employee_assignment_id', $assignmentIds)
                ->forceDelete();

            EmployeeAssignment::query()
                ->whereIn('id', $assignmentIds)
                ->forceDelete();
        }

        EmployeePosition::query()
            ->where('employee_id', $employee->id)
            ->forceDelete();
    }

    /**
     * @return array{0: Position, 1: Position|null}
     */
    private function pickPositionsForEmployee(Collection $positions, int $sequence, array $roleCodes): array
    {
        $weightedPositions = $this->buildWeightedPositionPool($positions, $roleCodes);
        $totalWeight = $weightedPositions->sum('weight');

        if ($totalWeight <= 0) {
            /** @var Position $fallbackPrimary */
            $fallbackPrimary = $positions[$sequence % $positions->count()];

            return [$fallbackPrimary, null];
        }

        /** @var Position $primary */
        $primary = $this->pickByWeight($weightedPositions, $sequence % $totalWeight);
        /** @var Position $secondary */
        $secondary = $this->pickByWeight($weightedPositions, ($sequence + 3) % $totalWeight);

        if ($secondary->id === $primary->id && $positions->count() > 1) {
            /** @var Position $candidate */
            $candidate = $positions[($sequence + 1) % $positions->count()];
            $secondary = $candidate;
        }

        if ($secondary->id === $primary->id) {
            $secondary = null;
        }

        return [$primary, $secondary];
    }

    /**
     * @return list<string>
     */
    private function roleCodesForEmployee(Employee $employee): array
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('employee_id', $employee->id)
            ->with('roles')
            ->first();

        if ($user === null) {
            return [Role::CODE_EMPLOYEE];
        }

        /** @var list<string> $codes */
        $codes = $user->roles->pluck('code')->values()->all();
        if ($codes === []) {
            return [Role::CODE_EMPLOYEE];
        }

        return $codes;
    }

    /**
     * @param  Collection<int, Position>  $positions
     * @return Collection<int, array{position: Position, weight: int}>
     */
    private function buildWeightedPositionPool(Collection $positions, array $roleCodes): Collection
    {
        $weights = [];

        foreach ($roleCodes as $roleCode) {
            foreach ($this->rolePositionWeights()[$roleCode] ?? [] as $positionCode => $weight) {
                $weights[$positionCode] = ($weights[$positionCode] ?? 0) + $weight;
            }
        }

        /** @var Collection<int, array{position: Position, weight: int}> $weighted */
        $weighted = $positions->map(function (Position $position) use ($weights): array {
            $weight = $weights[$position->code] ?? 1;

            return [
                'position' => $position,
                'weight' => max(1, $weight),
            ];
        })->values();

        return $weighted;
    }

    /**
     * @param  Collection<int, array{position: Position, weight: int}>  $weightedPositions
     */
    private function pickByWeight(Collection $weightedPositions, int $target): Position
    {
        $running = 0;

        foreach ($weightedPositions as $entry) {
            $running += $entry['weight'];
            if ($target < $running) {
                return $entry['position'];
            }
        }

        /** @var array{position: Position, weight: int} $last */
        $last = $weightedPositions->last();

        return $last['position'];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function rolePositionWeights(): array
    {
        return [
            Role::CODE_EMPLOYEE => [
                'LOAN-OFC' => 7,
                'MEMBER-SVC' => 7,
                'TELLER' => 6,
                'CREDIT-ANL' => 4,
                'BR-OPS-SUP' => 2,
            ],
            Role::CODE_HR_MANAGER => [
                'HR-SPEC' => 8,
                'PAYROLL-OFF' => 7,
                'COMPLIANCE-OFF' => 5,
                'HR-MGR' => 4,
            ],
            Role::CODE_HR_HEAD => [
                'HR-MGR' => 9,
                'COMPLIANCE-OFF' => 7,
                'PAYROLL-OFF' => 4,
                'HR-SPEC' => 3,
            ],
            Role::CODE_SUPER_ADMIN => [
                'IT-SUP' => 10,
                'COMPLIANCE-OFF' => 7,
                'HR-MGR' => 4,
                'BR-OPS-SUP' => 3,
            ],
        ];
    }

    /**
     * @return array{0: OrganizationalUnit, 1: OrganizationalUnit|null}
     */
    private function pickUnitsForEmployee(Collection $units, int $sequence): array
    {
        /** @var OrganizationalUnit $primary */
        $primary = $units[$sequence % $units->count()];

        $secondary = null;
        if ($units->count() > 1) {
            /** @var OrganizationalUnit $candidate */
            $candidate = $units[($sequence + 2) % $units->count()];
            if ($candidate->id !== $primary->id) {
                $secondary = $candidate;
            }
        }

        return [$primary, $secondary];
    }

    private function upsertEmployeePosition(
        Employee $employee,
        int $employmentId,
        Position $position,
        CarbonImmutable $startDate,
        ?CarbonImmutable $endDate,
        bool $isPrimary,
        string $notes
    ): EmployeePosition {
        /** @var EmployeePosition $row */
        $row = EmployeePosition::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'employee_employment_id' => $employmentId,
                'position_id' => $position->id,
                'start_date' => $startDate->toDateString(),
            ],
            [
                'is_primary' => $isPrimary,
                'end_date' => $endDate?->toDateString(),
                'notes' => $notes,
            ]
        );

        return $row;
    }

    private function upsertAssignmentsAndLinks(
        Employee $employee,
        int $sequence,
        OrganizationalUnit $primaryUnit,
        ?OrganizationalUnit $secondaryUnit,
        EmployeePosition $primaryEmployeePosition,
        ?EmployeePosition $secondaryEmployeePosition,
        array $roleCodes,
        int $employmentId,
        CarbonImmutable $hire,
    ): void {
        $isPrimaryHead = $this->shouldMarkAsHead($roleCodes, $sequence);

        $primaryStart = CarbonImmutable::create(2023, 1, 1)->addMonths($sequence);
        if ($primaryStart->lt($hire)) {
            $primaryStart = $hire;
        }
        $primaryStartDate = $primaryStart->toDateString();

        $this->assertEmployeeCanBeAssignedToUnit($employee, $primaryUnit, $primaryStartDate);

        EmployeeAssignment::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'organizational_unit_id' => $primaryUnit->id,
                'start_date' => $primaryStartDate,
            ],
            [
                'organization_id' => null,
                'employee_employment_id' => $employmentId,
                'end_date' => null,
                'is_primary' => true,
                'is_head' => $isPrimaryHead,
            ]
        );

        if ($secondaryUnit !== null && $secondaryEmployeePosition !== null) {
            $secondaryStart = CarbonImmutable::create(2021, 1, 1)->addMonths($sequence);
            if ($secondaryStart->lt($hire)) {
                $secondaryStart = $hire;
            }

            $secondaryEnd = CarbonImmutable::create(2022, 12, 31)->addDays($sequence % 27);
            if ($secondaryEnd->lt($secondaryStart)) {
                $secondaryEnd = $secondaryStart;
            }

            $secondaryStartDate = $secondaryStart->toDateString();
            $this->assertEmployeeCanBeAssignedToUnit($employee, $secondaryUnit, $secondaryStartDate);

            EmployeeAssignment::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'organizational_unit_id' => $secondaryUnit->id,
                    'start_date' => $secondaryStartDate,
                ],
                [
                    'organization_id' => null,
                    'employee_employment_id' => $employmentId,
                    'end_date' => $secondaryEnd->toDateString(),
                    'is_primary' => false,
                    'is_head' => false,
                ]
            );
        }

        $this->syncAssignmentPositions($employee);
    }

    /**
     * @param  list<string>  $roleCodes
     */
    private function shouldMarkAsHead(array $roleCodes, int $sequence): bool
    {
        if (in_array(Role::CODE_SUPER_ADMIN, $roleCodes, true) || in_array(Role::CODE_HR_HEAD, $roleCodes, true)) {
            return true;
        }

        if (in_array(Role::CODE_HR_MANAGER, $roleCodes, true) && $sequence % 4 === 0) {
            return true;
        }

        return false;
    }

    private function syncAssignmentPositions(Employee $employee): void
    {
        /** @var Collection<int, EmployeePosition> $employeePositions */
        $employeePositions = EmployeePosition::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->orderBy('id')
            ->get();

        /** @var Collection<int, EmployeeAssignment> $assignments */
        $assignments = EmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        foreach ($assignments as $assignment) {
            $candidate = $this->pickPositionForAssignment($assignment, $employeePositions);
            if ($candidate === null) {
                continue;
            }

            $this->upsertAssignmentPosition($assignment, $candidate, (bool) $assignment->is_primary);
        }
    }

    private function pickPositionForAssignment(EmployeeAssignment $assignment, Collection $employeePositions): ?EmployeePosition
    {
        /** @var Collection<int, EmployeePosition> $overlapping */
        $overlapping = $employeePositions->filter(function (EmployeePosition $position) use ($assignment): bool {
            $assignmentStart = CarbonImmutable::parse($assignment->start_date);
            $assignmentEnd = $assignment->end_date
                ? CarbonImmutable::parse($assignment->end_date)
                : CarbonImmutable::create(2099, 12, 31);
            $positionStart = CarbonImmutable::parse($position->start_date);
            $positionEnd = $position->end_date
                ? CarbonImmutable::parse($position->end_date)
                : CarbonImmutable::create(2099, 12, 31);

            return $assignmentStart->lte($positionEnd) && $positionStart->lte($assignmentEnd);
        })->values();

        if ($overlapping->isNotEmpty()) {
            /** @var Collection<int, EmployeePosition> $sorted */
            $sorted = $overlapping->sort(function (EmployeePosition $a, EmployeePosition $b) use ($assignment): int {
                $aMatch = (bool) $a->is_primary === (bool) $assignment->is_primary;
                $bMatch = (bool) $b->is_primary === (bool) $assignment->is_primary;
                if ($aMatch !== $bMatch) {
                    return $aMatch ? -1 : 1;
                }

                return strcmp((string) $b->start_date, (string) $a->start_date);
            })->values();

            /** @var EmployeePosition $best */
            $best = $sorted->first();

            return $best;
        }

        /** @var EmployeePosition|null $active */
        $active = $employeePositions->firstWhere('end_date', null);
        if ($active !== null) {
            return $active;
        }

        /** @var EmployeePosition|null $first */
        $first = $employeePositions->first();

        return $first;
    }

    private function upsertAssignmentPosition(EmployeeAssignment $assignment, EmployeePosition $employeePosition, bool $isPrimary): void
    {
        $startDate = CarbonImmutable::parse($assignment->start_date)->max(CarbonImmutable::parse($employeePosition->start_date));

        $assignmentEnd = $assignment->end_date
            ? CarbonImmutable::parse($assignment->end_date)
            : CarbonImmutable::create(2099, 12, 31);
        $positionEnd = $employeePosition->end_date
            ? CarbonImmutable::parse($employeePosition->end_date)
            : CarbonImmutable::create(2099, 12, 31);

        $endDate = $assignmentEnd->min($positionEnd);
        if ($endDate->lt($startDate)) {
            $endDate = $startDate;
        }

        AssignmentPosition::query()->updateOrCreate(
            [
                'employee_assignment_id' => $assignment->id,
                'employee_position_id' => $employeePosition->id,
                'start_date' => $startDate->toDateString(),
            ],
            [
                'end_date' => $endDate->toDateString(),
                'is_primary_for_assignment' => $isPrimary,
            ]
        );
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
                'notes' => 'Auto-created fallback by development position seeder.',
            ]);
        }

        return (int) $employment->id;
    }

    private function assertEmployeeCanBeAssignedToUnit(Employee $employee, OrganizationalUnit $unit, string $assignmentStartDate): void
    {
        $rootUnitId = $this->resolveRootUnitId($unit);

        $hasEligibleAffiliation = EmployeeAffiliation::query()
            ->where('employee_id', (int) $employee->id)
            ->where('organization_id', (int) $unit->organization_id)
            ->where(function ($query) use ($rootUnitId): void {
                $query->where('root_unit_id', $rootUnitId)
                    ->orWhereNull('root_unit_id');
            })
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $assignmentStartDate)
            ->where(function ($query) use ($assignmentStartDate): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $assignmentStartDate);
            })
            ->exists();

        if ($hasEligibleAffiliation) {
            return;
        }

        throw new \RuntimeException(
            "Cannot seed assignment for employee {$employee->id_number} in unit {$unit->code} without matching branch or org-wide affiliation."
        );
    }

    private function resolveRootUnitId(OrganizationalUnit $unit): int
    {
        $current = $unit;

        while ($current->parent_id !== null) {
            $parent = OrganizationalUnit::query()->find($current->parent_id);
            if ($parent === null) {
                break;
            }
            $current = $parent;
        }

        return (int) $current->id;
    }

    /**
     * @param  Collection<int, OrganizationalUnit>  $unitsByOrganization
     * @return Collection<int, OrganizationalUnit>
     */
    private function eligibleUnitsForEmployee(
        Employee $employee,
        Collection $unitsByOrganization,
        int $organizationId,
    ): Collection {
        $today = now()->toDateString();

        /** @var Collection<int, EmployeeAffiliation> $affiliations */
        $affiliations = EmployeeAffiliation::query()
            ->where('employee_id', (int) $employee->id)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->get(['root_unit_id']);

        if ($affiliations->isEmpty()) {
            return collect();
        }

        $hasOrgWide = $affiliations->contains(fn (EmployeeAffiliation $a): bool => $a->root_unit_id === null);
        if ($hasOrgWide) {
            return $unitsByOrganization->values();
        }

        /** @var list<int> $allowedRootIds */
        $allowedRootIds = $affiliations
            ->pluck('root_unit_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $unitsByOrganization
            ->filter(fn (OrganizationalUnit $unit): bool => in_array($this->resolveRootUnitId($unit), $allowedRootIds, true))
            ->values();
    }
}
