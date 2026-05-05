<?php

use App\Models\Organization;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('guests cannot check position code availability', function (): void {
    $this->getJson(route('positions.check-code-availability', [
        'code' => 'HR-MGR',
    ]))->assertUnauthorized();
});

test('employee role cannot check position code availability', function (): void {
    $employee = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($employee)
        ->getJson(route('positions.check-code-availability', [
            'code' => 'HR-MGR',
        ]))
        ->assertForbidden();
});

test('returns available when code does not exist in default organization', function (): void {
    config(['hris.default_organization_code' => 'T-POS-CODE-AVAIL']);
    Organization::factory()->create([
        'code' => 'T-POS-CODE-AVAIL',
        'is_active' => true,
    ]);

    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($manager)
        ->getJson(route('positions.check-code-availability', [
            'code' => 'hr-mgr',
        ]))
        ->assertOk()
        ->assertJsonPath('code.status', 'available');
});

test('returns taken when code already exists in default organization', function (): void {
    config(['hris.default_organization_code' => 'T-POS-CODE-TAKEN']);
    $organization = Organization::factory()->create([
        'code' => 'T-POS-CODE-TAKEN',
        'is_active' => true,
    ]);
    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'HR-MGR',
    ]);

    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($manager)
        ->getJson(route('positions.check-code-availability', [
            'code' => 'hr-mgr',
        ]))
        ->assertOk()
        ->assertJsonPath('code.status', 'taken');
});

test('ignore position id returns available for same existing record', function (): void {
    config(['hris.default_organization_code' => 'T-POS-CODE-IGNORE']);
    $organization = Organization::factory()->create([
        'code' => 'T-POS-CODE-IGNORE',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'HR-MGR',
    ]);

    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($manager)
        ->getJson(route('positions.check-code-availability', [
            'code' => 'hr-mgr',
            'ignore_position_id' => $position->id,
        ]))
        ->assertOk()
        ->assertJsonPath('code.status', 'available');
});

test('returns not found when default organization is missing', function (): void {
    config(['hris.default_organization_code' => '']);
    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($manager)
        ->getJson(route('positions.check-code-availability', [
            'code' => 'HR-MGR',
        ]))
        ->assertNotFound();
});
