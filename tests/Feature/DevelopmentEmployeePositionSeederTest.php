<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\DevelopmentEmployeePositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPmpcOrganization(): Organization
{
    /** @var Organization $organization */
    $organization = Organization::factory()->create([
        'code' => 'PMPC',
    ]);

    return $organization;
}

function createNonRootUnitType(): UnitType
{
    /** @var UnitType $unitType */
    $unitType = UnitType::factory()->create([
        'can_be_root' => false,
    ]);

    return $unitType;
}

function createActiveAffiliationForUnit(Employee $employee, OrganizationalUnit $unit): void
{
    $employment = EmployeeEmployment::query()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-01-01',
        'separation_date' => null,
        'separation_reason' => null,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'notes' => 'Created by DevelopmentEmployeePositionSeederTest.',
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $unit->organization_id,
        'root_unit_id' => null,
        'is_primary' => true,
        'start_date' => '2019-01-01',
        'end_date' => null,
    ]);
}

function runRoleAwareWeightingPrioritizesSuperAdminPositionPoolTest(): void
{
    $organization = createPmpcOrganization();
    $unitType = createNonRootUnitType();

    $unit = createPanD1S1Unit($organization, $unitType);
    seedRoleWeightingPositions($organization);

    $employee = Employee::factory()->create([
        'id_number' => 'EMP-SEED-200',
    ]);
    createActiveAffiliationForUnit($employee, $unit);
    createUserWithEmployeeAndSuperAdminRoles($employee);

    (new DevelopmentEmployeePositionSeeder)->run();

    $codes = seededPositionCodesForEmployee($employee);

    expect($codes)->not->toBeEmpty();
    expect(collect($codes)->contains(fn (string $code): bool => in_array($code, ['IT-SUP', 'COMPLIANCE-OFF'], true)))->toBeTrue();
    expect(EmployeeAssignment::query()->where('employee_id', $employee->id)->where('is_head', true)->exists())->toBeTrue();
}

function createPanD1S1Unit(Organization $organization, UnitType $unitType): OrganizationalUnit
{
    /** @var OrganizationalUnit $unit */
    $unit = OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'PAN-D1-S1',
        'unit_type_id' => $unitType->id,
    ]);

    return $unit;
}

function seedRoleWeightingPositions(Organization $organization): void
{
    Position::factory()->for($organization)->create([
        'code' => 'IT-SUP',
        'title' => 'IT Support Specialist',
        'is_active' => true,
    ]);
    Position::factory()->for($organization)->create([
        'code' => 'COMPLIANCE-OFF',
        'title' => 'Compliance Officer',
        'is_active' => true,
    ]);
    Position::factory()->for($organization)->create([
        'code' => 'HR-SPEC',
        'title' => 'HR Specialist',
        'is_active' => true,
    ]);
}

function createUserWithEmployeeAndSuperAdminRoles(Employee $employee): void
{
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $roleEmployee = Role::query()->create([
        'code' => Role::CODE_EMPLOYEE,
        'name' => 'Employee',
        'description' => null,
    ]);
    $roleSuperAdmin = Role::query()->create([
        'code' => Role::CODE_SUPER_ADMIN,
        'name' => 'Super Administrator',
        'description' => null,
    ]);

    $user->roles()->sync([$roleEmployee->id, $roleSuperAdmin->id]);
}

/**
 * @return list<string>
 */
function seededPositionCodesForEmployee(Employee $employee): array
{
    /** @var list<string> $codes */
    $codes = EmployeePosition::query()
        ->where('employee_id', $employee->id)
        ->with('position:id,code')
        ->get()
        ->pluck('position.code')
        ->filter()
        ->values()
        ->all();

    return $codes;
}

test('development employee position seeder creates deterministic and idempotent links', function () {
    $organization = createPmpcOrganization();
    $unitType = createNonRootUnitType();

    $unitA = OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'PAN-D1-S1',
        'unit_type_id' => $unitType->id,
    ]);
    OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'PAN-D1-S2',
        'unit_type_id' => $unitType->id,
    ]);

    Position::factory()->for($organization)->create([
        'code' => 'HR-MGR',
        'title' => 'HR Manager',
        'is_active' => true,
    ]);
    Position::factory()->for($organization)->create([
        'code' => 'HR-SPEC',
        'title' => 'HR Specialist',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'id_number' => 'EMP-SEED-099',
    ]);
    createActiveAffiliationForUnit($employee, $unitA);

    (new DevelopmentEmployeePositionSeeder)->run();

    expect(EmployeePosition::query()->where('employee_id', $employee->id)->count())->toBeGreaterThan(0)
        ->and(EmployeeAssignment::query()->where('employee_id', $employee->id)->count())->toBeGreaterThan(0);
    expect(
        EmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('employee_employment_id')
            ->count()
    )->toBe($employee->assignments()->count());
    expect(
        EmployeeEmployment::query()
            ->where('employee_id', $employee->id)
            ->where('is_current', true)
            ->exists()
    )->toBeTrue();

    $assignmentLinksCount = $employee->assignments()
        ->withCount('assignmentPositions')
        ->get()
        ->sum('assignment_positions_count');

    expect($assignmentLinksCount)->toBeGreaterThan(0);
    expect(EmployeeAssignment::query()->where('employee_id', $employee->id)->where('is_head', true)->exists())->toBeFalse();
    expect(Position::query()->where('title', 'like', '%Team%')->exists())->toBeFalse();

    $employeePositionCount = EmployeePosition::query()->where('employee_id', $employee->id)->count();
    $employeeAssignmentCount = EmployeeAssignment::query()->where('employee_id', $employee->id)->count();
    $assignmentPositionCount = $employee->assignments()
        ->withCount('assignmentPositions')
        ->get()
        ->sum('assignment_positions_count');

    (new DevelopmentEmployeePositionSeeder)->run();

    expect(EmployeePosition::query()->where('employee_id', $employee->id)->count())->toBe($employeePositionCount)
        ->and(EmployeeAssignment::query()->where('employee_id', $employee->id)->count())->toBe($employeeAssignmentCount)
        ->and($employee->assignments()->withCount('assignmentPositions')->get()->sum('assignment_positions_count'))->toBe($assignmentPositionCount);
});

test('role-aware weighting prioritizes super admin position pool', function () {
    runRoleAwareWeightingPrioritizesSuperAdminPositionPoolTest();
});

test('head-role employees are marked as unit head deterministically', function () {
    $organization = createPmpcOrganization();
    $unitType = createNonRootUnitType();

    $unit = OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'PAN-D2-S1',
        'unit_type_id' => $unitType->id,
    ]);

    Position::factory()->for($organization)->create([
        'code' => 'HR-MGR',
        'title' => 'HR Manager',
        'is_active' => true,
    ]);
    Position::factory()->for($organization)->create([
        'code' => 'COMPLIANCE-OFF',
        'title' => 'Compliance Officer',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'id_number' => 'EMP-SEED-300',
    ]);
    createActiveAffiliationForUnit($employee, $unit);
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $roleHrHead = Role::query()->create([
        'code' => Role::CODE_HR_HEAD,
        'name' => 'HR Head',
        'description' => null,
    ]);
    $user->roles()->sync([$roleHrHead->id]);

    (new DevelopmentEmployeePositionSeeder)->run();

    expect(
        EmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->where('is_head', true)
            ->exists()
    )->toBeTrue();
});

test('Diego Navarro demo employee is assigned to Panabo interns section', function () {
    $organization = createPmpcOrganization();
    $unitType = createNonRootUnitType();

    $panSection = OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'PAN-D2-S1',
        'unit_type_id' => $unitType->id,
    ]);

    OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'TAG',
        'unit_type_id' => $unitType->id,
    ]);

    Position::factory()->for($organization)->create([
        'code' => 'HR-SPEC',
        'title' => 'HR Specialist',
        'is_active' => true,
    ]);
    Position::factory()->for($organization)->create([
        'code' => 'MEMBER-SVC',
        'title' => 'Member Services Officer',
        'is_active' => true,
    ]);

    $diego = Employee::factory()->create([
        'id_number' => 'EMP-SEED-011',
        'first_name' => 'Diego',
        'last_name' => 'Navarro',
    ]);
    createActiveAffiliationForUnit($diego, $panSection);

    $roleEmployee = Role::query()->firstOrCreate(
        ['code' => Role::CODE_EMPLOYEE],
        ['name' => 'Employee', 'description' => null],
    );

    $user = User::factory()->create([
        'employee_id' => $diego->id,
    ]);
    $user->roles()->sync([$roleEmployee->id]);

    (new DevelopmentEmployeePositionSeeder)->run();

    expect(
        EmployeeAssignment::query()
            ->where('employee_id', $diego->id)
            ->where('organizational_unit_id', $panSection->id)
            ->where('is_primary', true)
            ->where('is_head', false)
            ->whereDate('end_date', '2025-03-31')
            ->exists()
    )->toBeTrue();
});

test('seeder throws when employee assignment unit has no matching affiliation', function () {
    $organization = createPmpcOrganization();
    $unitType = createNonRootUnitType();

    OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'PAN-D1-S1',
        'unit_type_id' => $unitType->id,
    ]);

    Position::factory()->for($organization)->create([
        'code' => 'HR-MGR',
        'title' => 'HR Manager',
        'is_active' => true,
    ]);
    Position::factory()->for($organization)->create([
        'code' => 'LOAN-OFC',
        'title' => 'Loan Officer',
        'is_active' => true,
    ]);

    Employee::factory()->create([
        'id_number' => 'EMP-SEED-003',
    ]);

    expect(fn () => (new DevelopmentEmployeePositionSeeder)->run())
        ->toThrow(RuntimeException::class);
});

test('seeder allows assignment when employee has org-wide affiliation', function () {
    $organization = createPmpcOrganization();
    $unitType = createNonRootUnitType();

    $unit = OrganizationalUnit::factory()->for($organization)->create([
        'code' => 'PAN-D1-S1',
        'unit_type_id' => $unitType->id,
    ]);

    Position::factory()->for($organization)->create([
        'code' => 'LOAN-OFC',
        'title' => 'Loan Officer',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'id_number' => 'EMP-SEED-401',
    ]);

    $employment = EmployeeEmployment::query()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-01-01',
        'separation_date' => null,
        'separation_reason' => null,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'notes' => 'Org-wide affiliation test fixture.',
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'is_primary' => true,
        'start_date' => '2019-01-01',
        'end_date' => null,
    ]);

    (new DevelopmentEmployeePositionSeeder)->run();

    expect(
        EmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('organizational_unit_id', $unit->id)
            ->exists()
    )->toBeTrue();
});
