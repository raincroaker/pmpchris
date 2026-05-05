<?php

use App\Models\AssignmentPosition;
use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUnitAssignedEmployeeWithTwoPositions(OrganizationalUnit $root, OrganizationalUnit $unit): array
{
    $employee = Employee::factory()->create([
        'first_name' => 'Nina',
        'last_name' => 'Assigned',
        'middle_name' => null,
        'suffix' => null,
        'id_number' => 'EMP-NINA',
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $root->organization_id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $positionA = Position::factory()->create([
        'organization_id' => $root->organization_id,
        'title' => 'Staff A',
    ]);
    $positionB = Position::factory()->create([
        'organization_id' => $root->organization_id,
        'title' => 'Staff B',
    ]);

    $employeePositionA = EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $positionA->id,
        'is_primary' => true,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
    ]);
    $employeePositionB = EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $positionB->id,
        'is_primary' => false,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
    ]);

    $assignment = EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    AssignmentPosition::query()->create([
        'employee_assignment_id' => $assignment->id,
        'employee_position_id' => $employeePositionA->id,
        'is_primary_for_assignment' => true,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addYears(20)->toDateString(),
    ]);

    return [
        'employee' => $employee,
        'employment' => $employment,
        'assignment' => $assignment,
        'positionA' => $positionA,
        'positionB' => $positionB,
        'employeePositionA' => $employeePositionA,
        'employeePositionB' => $employeePositionB,
    ];
}

function createEligibleEmployeeWithTwoPositions(OrganizationalUnit $root): array
{
    $suffix = (string) fake()->unique()->numberBetween(1000, 9999);

    $employee = Employee::factory()->create([
        'first_name' => 'Lia',
        'last_name' => 'Candidate',
        'middle_name' => null,
        'suffix' => null,
        'id_number' => 'EMP-LIA-'.$suffix,
    ]);

    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $root->organization_id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
    ]);

    $positionA = Position::factory()->create([
        'organization_id' => $root->organization_id,
        'title' => 'Analyst',
    ]);
    $positionB = Position::factory()->create([
        'organization_id' => $root->organization_id,
        'title' => 'Coordinator',
    ]);

    $employeePositionA = EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $positionA->id,
        'is_primary' => true,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
    ]);
    $employeePositionB = EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $positionB->id,
        'is_primary' => false,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => null,
    ]);

    return [
        'employee' => $employee,
        'employment' => $employment,
        'positionA' => $positionA,
        'positionB' => $positionB,
        'employeePositionA' => $employeePositionA,
        'employeePositionB' => $employeePositionB,
    ];
}

beforeEach(function (): void {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);
});

test('hr head can update unit employee assignment position and head flag', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();

    $setup = createUnitAssignedEmployeeWithTwoPositions($panabo, $panaboSection);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $effectiveDate = now()->addDay()->toDateString();
    $expectedPreviousEndDate = now()->toDateString();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.employees.update', ['assignment' => $setup['assignment']->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'position_id' => $setup['positionB']->id,
            'effective_date' => $effectiveDate,
            'is_primary' => false,
            'is_head' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.assignment_id', $setup['assignment']->id)
        ->assertJsonPath('data.position_title', 'Staff B')
        ->assertJsonPath('data.is_head', true);

    $setup['assignment']->refresh();
    expect($setup['assignment']->is_head)->toBeTrue()
        ->and($setup['assignment']->is_primary)->toBeFalse();

    $today = $effectiveDate;
    $active = AssignmentPosition::query()
        ->where('employee_assignment_id', $setup['assignment']->id)
        ->whereNull('deleted_at')
        ->whereDate('end_date', '>=', $today)
        ->get();

    expect($active)->toHaveCount(1)
        ->and((int) $active->first()->employee_position_id)->toBe((int) $setup['employeePositionB']->id);

    $previousLink = AssignmentPosition::query()
        ->where('employee_assignment_id', $setup['assignment']->id)
        ->where('employee_position_id', $setup['employeePositionA']->id)
        ->firstOrFail();
    expect($previousLink->end_date?->toDateString())->toBe($expectedPreviousEndDate);
});

test('hr manager cannot update unit assignment in unmanaged branch', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $tagum */
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $tagumSection */
    $tagumSection = OrganizationalUnit::query()->where('code', 'TAG-D1-S1')->firstOrFail();

    $setup = createUnitAssignedEmployeeWithTwoPositions($tagum, $tagumSection);

    /** @var User $hrManager */
    $hrManager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $hrManager->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.employees.update', ['assignment' => $setup['assignment']->id]), [
            'chart_branch_id' => $tagum->id,
            'node_id' => 'unit-'.$tagumSection->id,
            'position_id' => $setup['positionB']->id,
            'effective_date' => now()->toDateString(),
            'is_primary' => true,
            'is_head' => true,
        ])
        ->assertForbidden();
});

test('hr head can create unit employee assignment', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();

    $setup = createEligibleEmployeeWithTwoPositions($panabo);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $startDate = now()->addDays(2)->toDateString();

    $response = $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.employees.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'employee_id' => $setup['employee']->id,
            'position_id' => $setup['positionB']->id,
            'start_date' => $startDate,
            'is_primary' => true,
            'is_head' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.employee_id', $setup['employee']->id)
        ->assertJsonPath('data.position_title', 'Coordinator')
        ->assertJsonPath('data.is_head', true);

    $assignmentId = (int) $response->json('data.assignment_id');
    $assignment = EmployeeAssignment::query()->with('assignmentPositions')->findOrFail($assignmentId);

    expect((int) $assignment->organizational_unit_id)->toBe((int) $panaboSection->id)
        ->and((int) $assignment->employee_employment_id)->toBe((int) $setup['employment']->id)
        ->and($assignment->start_date?->toDateString())->toBe($startDate)
        ->and($assignment->is_primary)->toBeTrue()
        ->and($assignment->is_head)->toBeTrue();

    $activeLink = AssignmentPosition::query()
        ->where('employee_assignment_id', $assignmentId)
        ->where('is_primary_for_assignment', true)
        ->first();

    expect($activeLink)->not->toBeNull()
        ->and((int) $activeLink->employee_position_id)->toBe((int) $setup['employeePositionB']->id);

    $search = $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'q' => 'EMP-LIA',
        ]))
        ->assertOk();

    $returned = collect($search->json('data'))->pluck('employee_number')->all();
    expect($returned)->not->toContain('EMP-LIA');
});

test('hr manager cannot create unit assignment in unmanaged branch', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $tagum */
    $tagum = OrganizationalUnit::query()->where('code', 'TAG')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $tagumSection */
    $tagumSection = OrganizationalUnit::query()->where('code', 'TAG-D1-S1')->firstOrFail();

    $setup = createEligibleEmployeeWithTwoPositions($tagum);

    /** @var User $hrManager */
    $hrManager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $hrManager->id,
        'root_unit_id' => $panabo->id,
        'is_active' => true,
    ]);

    $this->actingAs($hrManager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.employees.store'), [
            'chart_branch_id' => $tagum->id,
            'node_id' => 'unit-'.$tagumSection->id,
            'employee_id' => $setup['employee']->id,
            'position_id' => $setup['positionA']->id,
            'start_date' => now()->toDateString(),
            'is_primary' => false,
            'is_head' => false,
        ])
        ->assertForbidden();
});

test('create assignment rejects duplicate active employee in same unit', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();

    $setup = createUnitAssignedEmployeeWithTwoPositions($panabo, $panaboSection);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.employees.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'employee_id' => $setup['employee']->id,
            'position_id' => $setup['positionB']->id,
            'start_date' => now()->toDateString(),
            'is_primary' => false,
            'is_head' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id']);
});

test('create assignment allows multiple primary employees in the same unit', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();

    $first = createEligibleEmployeeWithTwoPositions($panabo);
    $second = createEligibleEmployeeWithTwoPositions($panabo);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $startDate = now()->toDateString();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.employees.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'employee_id' => $first['employee']->id,
            'position_id' => $first['positionA']->id,
            'start_date' => $startDate,
            'is_primary' => true,
            'is_head' => false,
        ])
        ->assertSuccessful();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.employees.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'employee_id' => $second['employee']->id,
            'position_id' => $second['positionA']->id,
            'start_date' => $startDate,
            'is_primary' => true,
            'is_head' => false,
        ])
        ->assertSuccessful();

    $primaryCount = EmployeeAssignment::query()
        ->where('organizational_unit_id', $panaboSection->id)
        ->where('is_primary', true)
        ->whereNull('deleted_at')
        ->where(function ($query): void {
            $query->whereNull('end_date')
                ->orWhereDate('end_date', '>=', now()->toDateString());
        })
        ->count();

    expect($primaryCount)->toBeGreaterThanOrEqual(2);
});

test('create assignment rejects position not owned by selected employee', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();

    $candidate = createEligibleEmployeeWithTwoPositions($panabo);
    $other = createEligibleEmployeeWithTwoPositions($panabo);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->postJson(route('organization-chart.employees.store'), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'employee_id' => $candidate['employee']->id,
            'position_id' => $other['positionA']->id,
            'start_date' => now()->toDateString(),
            'is_primary' => false,
            'is_head' => false,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['position_id']);
});

test('hr head can remove unit assignment and it becomes available for search', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();

    $setup = createUnitAssignedEmployeeWithTwoPositions($panabo, $panaboSection);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->deleteJson(route('organization-chart.employees.destroy', ['assignment' => $setup['assignment']->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'end_date' => now()->addDay()->toDateString(),
        ])
        ->assertSuccessful();

    $setup['assignment']->refresh();
    expect($setup['assignment']->end_date)->not->toBeNull()
        ->and($setup['assignment']->deleted_at)->not->toBeNull();

    $search = $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('organization-chart.employees.search', [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'q' => 'EMP-NINA',
        ]))
        ->assertOk();

    $returned = collect($search->json('data'))->pluck('employee_number')->all();
    expect($returned)->toContain('EMP-NINA');
});

test('employee assignment mutations reject overall chart scope context', function () {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();
    $setup = createUnitAssignedEmployeeWithTwoPositions($panabo, $panaboSection);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->patchJson(route('organization-chart.employees.update', ['assignment' => $setup['assignment']->id]), [
            'chart_scope' => 'all',
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'position_id' => $setup['positionB']->id,
            'effective_date' => now()->toDateString(),
            'is_primary' => false,
            'is_head' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chart_scope']);
});

test('remove assignment rejects past end date', function (): void {
    /** @var OrganizationalUnit $panabo */
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();
    /** @var OrganizationalUnit $panaboSection */
    $panaboSection = OrganizationalUnit::query()->where('code', 'PAN-D1-S1')->firstOrFail();
    $setup = createUnitAssignedEmployeeWithTwoPositions($panabo, $panaboSection);

    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->deleteJson(route('organization-chart.employees.destroy', ['assignment' => $setup['assignment']->id]), [
            'chart_branch_id' => $panabo->id,
            'node_id' => 'unit-'.$panaboSection->id,
            'end_date' => now()->subDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});
