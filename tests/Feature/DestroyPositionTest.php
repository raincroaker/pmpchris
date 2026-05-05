<?php

use App\Models\Employee;
use App\Models\EmployeePosition;
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

test('guests cannot delete positions', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-DEL-GUEST',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->deleteJson(route('positions.destroy', $position))
        ->assertUnauthorized();
});

test('employee role cannot delete positions', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-DEL-EMP',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->deleteJson(route('positions.destroy', $position))
        ->assertForbidden();
});

test('privileged user can delete unused position in default organization', function (): void {
    config(['hris.default_organization_code' => 'T-POS-DEL-OK']);

    $organization = Organization::factory()->create([
        'code' => 'T-POS-DEL-OK',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->deleteJson(route('positions.destroy', $position))
        ->assertOk()
        ->assertJsonPath('data.id', $position->id);

    expect(Position::query()->whereKey($position->id)->exists())->toBeFalse();
});

test('delete position is rejected when referenced by employee records', function (): void {
    config(['hris.default_organization_code' => 'T-POS-DEL-REF']);

    $organization = Organization::factory()->create([
        'code' => 'T-POS-DEL-REF',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $employee = Employee::factory()->create();
    EmployeePosition::factory()->create([
        'employee_id' => $employee->id,
        'position_id' => $position->id,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->deleteJson(route('positions.destroy', $position))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['position']);

    expect(Position::query()->whereKey($position->id)->exists())->toBeTrue();
});

test('position outside default organization cannot be deleted', function (): void {
    config(['hris.default_organization_code' => 'T-POS-DEL-DEF']);

    Organization::factory()->create([
        'code' => 'T-POS-DEL-DEF',
        'is_active' => true,
    ]);
    $otherOrganization = Organization::factory()->create([
        'code' => 'T-POS-DEL-OTHER',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->deleteJson(route('positions.destroy', $position))
        ->assertNotFound();
});
