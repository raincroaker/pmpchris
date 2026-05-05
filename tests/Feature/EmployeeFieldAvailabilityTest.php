<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
});

function createAvailabilityRoot(Organization $organization): OrganizationalUnit
{
    $unitType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);

    return OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);
}

test('authorized hr roles can check employee field availability', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-AVAIL', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-AVAIL']);
    $root = createAvailabilityRoot($organization);

    Employee::factory()->create([
        'id_number' => 'EMP-TAKEN-1',
        'attendance_id' => 'ATT-TAKEN-1',
    ]);
    User::factory()->create(['email' => 'taken@example.com']);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->get(route('employees.check-availability', [
            'id_number' => 'EMP-TAKEN-1',
            'attendance_id' => 'ATT-TAKEN-1',
            'email' => 'taken@example.com',
        ]));

    $response->assertSuccessful()
        ->assertJsonPath('id_number.status', 'taken')
        ->assertJsonPath('attendance_id.status', 'taken')
        ->assertJsonPath('email.status', 'taken');
});

test('hr head can check employee field availability', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-AVAIL-SYSADMIN', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-AVAIL-SYSADMIN']);
    $root = createAvailabilityRoot($organization);

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->get(route('employees.check-availability', [
            'id_number' => 'EMP-FREE-1',
            'attendance_id' => 'ATT-FREE-1',
            'email' => 'available@example.com',
        ]))
        ->assertSuccessful()
        ->assertJsonPath('id_number.status', 'available')
        ->assertJsonPath('attendance_id.status', 'available')
        ->assertJsonPath('email.status', 'available');
});

test('non hr roles cannot check employee field availability', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-AVAIL-FORBID', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-AVAIL-FORBID']);
    $root = createAvailabilityRoot($organization);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->get(route('employees.check-availability'))
        ->assertForbidden();
});
