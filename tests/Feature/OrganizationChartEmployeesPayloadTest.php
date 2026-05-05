<?php

use App\Models\AssignmentPosition;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\OrganizationChartDataService;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('organization chart node payload includes employees with assignment-linked position titles', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $organization = Organization::factory()->create([
        'code' => 'T-ORG',
        'name' => 'Test Cooperative',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-ORG']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-TEST',
        'name' => 'Test Branch',
        'is_active' => true,
    ]);

    $position = Position::factory()->for($organization)->create([
        'code' => 'TEST-POS',
        'title' => 'Senior Analyst',
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create([
        'first_name' => 'Jane',
        'middle_name' => null,
        'last_name' => 'Doe',
        'suffix' => null,
    ]);

    $employeePosition = EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2023-01-01',
        'end_date' => null,
    ]);

    $assignment = EmployeeAssignment::factory()->create([
        'employee_id' => $employee->id,
        'organizational_unit_id' => $branch->id,
        'is_primary' => true,
        'is_head' => true,
        'start_date' => '2023-01-01',
        'end_date' => null,
    ]);

    AssignmentPosition::query()->create([
        'employee_assignment_id' => $assignment->id,
        'employee_position_id' => $employeePosition->id,
        'is_primary_for_assignment' => true,
        'start_date' => '2023-01-01',
        'end_date' => now()->addYears(20)->toDateString(),
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $request = Request::create('/organization-chart', 'GET');
    $request->setUserResolver(fn () => $user);

    $result = app(OrganizationChartDataService::class)->buildForRequest($request);

    expect($result['orgChart'])->not->toBeNull();

    $nodes = $result['orgChart']['nodes'];
    $unitNode = collect($nodes)->firstWhere('id', 'unit-'.$branch->id);

    expect($unitNode)->not->toBeNull();
    expect($unitNode['data']['employees'] ?? [])->toBeArray()->not->toBeEmpty();

    $rows = $unitNode['data']['employees'];
    expect($rows[0]['full_name'])->toBe('Jane Doe');
    expect($rows[0]['position_title'])->toBe('Senior Analyst');
    expect($rows[0]['employee_id'])->toBe($employee->id);
    expect($rows[0]['is_primary'])->toBeTrue();
    expect($rows[0]['is_head'])->toBeTrue();
});

test('organization chart payload excludes inactive and already separated employees', function () {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();

    $organization = Organization::factory()->create([
        'code' => 'T-ORG',
        'name' => 'Test Cooperative',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-ORG']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-TEST',
        'name' => 'Test Branch',
        'is_active' => true,
    ]);

    $activeEmployee = Employee::factory()->create([
        'first_name' => 'Active',
        'middle_name' => null,
        'last_name' => 'Employee',
        'suffix' => null,
    ]);
    $inactiveEmployee = Employee::factory()->create([
        'first_name' => 'Inactive',
        'middle_name' => null,
        'last_name' => 'Employee',
        'suffix' => null,
    ]);
    $separatedEmployee = Employee::factory()->create([
        'first_name' => 'Separated',
        'middle_name' => null,
        'last_name' => 'Employee',
        'suffix' => null,
    ]);

    $activeEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $activeEmployee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'separation_date' => null,
    ]);
    $inactiveEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $inactiveEmployee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_TERMINATED,
        'separation_date' => now()->subDay()->toDateString(),
    ]);
    $separatedEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $separatedEmployee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'separation_date' => now()->subDay()->toDateString(),
    ]);

    EmployeeAssignment::factory()->create([
        'employee_id' => $activeEmployee->id,
        'employee_employment_id' => $activeEmployment->id,
        'organizational_unit_id' => $branch->id,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => '2023-01-01',
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $inactiveEmployee->id,
        'employee_employment_id' => $inactiveEmployment->id,
        'organizational_unit_id' => $branch->id,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => '2023-01-01',
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $separatedEmployee->id,
        'employee_employment_id' => $separatedEmployment->id,
        'organizational_unit_id' => $branch->id,
        'is_primary' => true,
        'is_head' => false,
        'start_date' => '2023-01-01',
        'end_date' => null,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $request = Request::create('/organization-chart', 'GET');
    $request->setUserResolver(fn () => $user);

    $result = app(OrganizationChartDataService::class)->buildForRequest($request);

    expect($result['orgChart'])->not->toBeNull();

    $nodes = $result['orgChart']['nodes'];
    $unitNode = collect($nodes)->firstWhere('id', 'unit-'.$branch->id);

    expect($unitNode)->not->toBeNull();

    $employeeIds = collect($unitNode['data']['employees'] ?? [])
        ->pluck('employee_id')
        ->all();

    expect($employeeIds)->toContain($activeEmployee->id)
        ->not->toContain($inactiveEmployee->id)
        ->not->toContain($separatedEmployee->id);
});
