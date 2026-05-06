<?php

use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    config([
        'hris.branch_picker_enabled' => false,
        'hris.default_organization_code' => 'T-SYNC-PA',
    ]);
});

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function syncCatalogAuthPayload(array $data): array
{
    return array_merge([
        'current_password' => 'password',
        'current_password_confirmation' => 'password',
    ], $data);
}

/** @return array{organization: Organization, root: OrganizationalUnit, employee: Employee, employment: EmployeeEmployment, position: Position, employeePosition: EmployeePosition, affiliation: EmployeeAffiliation} */
function syncCatalogBootstrap(): array
{
    $organization = Organization::factory()->create([
        'code' => 'T-SYNC-PA',
        'is_active' => true,
    ]);

    $unitType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'hire_date' => '2020-06-01',
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'SYNC-POS',
        'title' => 'Sync Position',
        'is_active' => true,
    ]);

    $employeePosition = EmployeePosition::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'position_id' => $position->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
        'notes' => null,
    ]);

    $affiliation = EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'is_primary' => true,
        'start_date' => '2020-06-01',
        'end_date' => null,
    ]);

    return [
        'organization' => $organization,
        'root' => $root,
        'employee' => $employee,
        'employment' => $employment,
        'position' => $position,
        'employeePosition' => $employeePosition,
        'affiliation' => $affiliation,
    ];
}

test('guests cannot sync employment positions affiliations', function (): void {
    $boot = syncCatalogBootstrap();

    $this->patch(route('employees.employments.sync-positions-affiliations', ['employment' => $boot['employment']->id]), [
        'positions' => [],
        'affiliations' => [],
    ])->assertRedirect(route('login'));
});

test('employee cannot sync catalog rows for another users employment', function (): void {
    $boot = syncCatalogBootstrap();

    /** @var User $intruder */
    $intruder = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => Employee::factory(),
    ]);

    $payload = syncCatalogAuthPayload([
        'positions' => [
            [
                'id' => $boot['employeePosition']->id,
                'position_id' => $boot['position']->id,
                'start_date' => '2020-06-01',
                'end_date' => null,
                'is_primary' => true,
            ],
        ],
        'affiliations' => [
            [
                'id' => $boot['affiliation']->id,
                'root_unit_id' => $boot['root']->id,
                'start_date' => '2020-06-01',
                'end_date' => null,
                'is_primary' => true,
            ],
        ],
    ]);

    $this->actingAs($intruder)
        ->patch(
            route('employees.employments.sync-positions-affiliations', ['employment' => $boot['employment']->id]),
            $payload,
        )
        ->assertForbidden();
});

test('employee role cannot persist employment positions affiliations sync', function (): void {
    $boot = syncCatalogBootstrap();

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create([
        'employee_id' => $boot['employee']->id,
    ]);

    $payload = syncCatalogAuthPayload([
        'positions' => [
            [
                'id' => $boot['employeePosition']->id,
                'position_id' => $boot['position']->id,
                'start_date' => '2020-06-01',
                'end_date' => null,
                'is_primary' => true,
            ],
        ],
        'affiliations' => [
            [
                'id' => $boot['affiliation']->id,
                'root_unit_id' => $boot['root']->id,
                'start_date' => '2020-06-01',
                'end_date' => null,
                'is_primary' => true,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->patch(
            route('employees.employments.sync-positions-affiliations', ['employment' => $boot['employment']->id]),
            $payload,
        )
        ->assertForbidden();

    expect((int) EmployeePosition::query()
        ->where('employee_employment_id', $boot['employment']->id)
        ->whereNull('deleted_at')
        ->count())->toBe(1)
        ->and((int) EmployeeAffiliation::query()
            ->where('employee_employment_id', $boot['employment']->id)
            ->whereNull('deleted_at')
            ->count())->toBe(1);
});
