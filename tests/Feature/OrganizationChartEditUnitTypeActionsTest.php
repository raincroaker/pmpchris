<?php

use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new OrganizationalStructureSeeder)->run();
    (new RoleSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);
    config(['hris.branch_picker_enabled' => false]);
});

test('authorized editor can deactivate a referenced unit type', function (): void {
    /** @var UnitType $branchType */
    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->from(route('organization-chart.edit'))
        ->patch(route('organization-chart.edit.unit-types.deactivate', $branchType))
        ->assertRedirect(route('organization-chart.edit'));

    expect($branchType->fresh()?->is_active)->toBeFalse();
});

test('authorized editor cannot delete a unit type still referenced by organizational units', function (): void {
    /** @var UnitType $branchType */
    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->from(route('organization-chart.edit'))
        ->delete(route('organization-chart.edit.unit-types.destroy', $branchType))
        ->assertRedirect(route('organization-chart.edit'))
        ->assertSessionHasErrors('unit_type');

    expect(UnitType::query()->whereKey($branchType->id)->exists())->toBeTrue();
});

test('authorized editor can permanently delete an unreferenced unit type', function (): void {
    /** @var UnitType $type */
    $type = UnitType::query()->create([
        'name' => 'Delete Me Type',
        'color' => '#6366f1',
        'can_be_root' => false,
        'description' => 'For delete endpoint test',
        'is_active' => true,
    ]);
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->from(route('organization-chart.edit'))
        ->delete(route('organization-chart.edit.unit-types.destroy', $type))
        ->assertRedirect(route('organization-chart.edit'));

    expect(UnitType::query()->whereKey($type->id)->exists())->toBeFalse();
});

test('non-editor roles are forbidden from deactivate and delete actions', function (): void {
    /** @var UnitType $type */
    $type = UnitType::query()->where('name', 'Department')->firstOrFail();
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->patch(route('organization-chart.edit.unit-types.deactivate', $type))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('organization-chart.edit.unit-types.destroy', $type))
        ->assertForbidden();
});
