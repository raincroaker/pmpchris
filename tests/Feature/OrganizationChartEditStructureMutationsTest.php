<?php

use App\Models\Area;
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

test('editor role can create and update unit type including color', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $branch = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $this->actingAs($user)
        ->from(route('organization-chart.edit'))
        ->post(route('organization-chart.edit.unit-types.store'), [
            'name' => 'Temporary Type',
            'description' => '',
            'color' => '#65a30d',
            'can_be_root' => false,
            'parent_type_ids' => [$branch->id],
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 10,
            'page' => 1,
        ])
        ->assertRedirect();

    /** @var UnitType $created */
    $created = UnitType::query()->where('name', 'Temporary Type')->firstOrFail();
    expect($created->color)->toBe('#65a30d');

    $this->actingAs($user)
        ->from(route('organization-chart.edit'))
        ->patch(route('organization-chart.edit.unit-types.update', $created), [
            'name' => 'Temporary Type',
            'description' => '',
            'color' => '#f43f5e',
            'can_be_root' => false,
            'parent_type_ids' => [$branch->id],
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 10,
            'page' => 1,
        ])
        ->assertRedirect();

    expect($created->fresh()?->color)->toBe('#f43f5e');
});

test('non editor role is forbidden from edit structure mutation endpoints', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();
    $department = UnitType::query()->where('name', 'Department')->firstOrFail();

    $this->actingAs($user)
        ->post(route('organization-chart.edit.unit-types.store'), [
            'name' => 'Blocked Type',
            'color' => '#6366f1',
            'can_be_root' => true,
            'parent_type_ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('organization-chart.edit.unit-types.update', $department), [
            'name' => 'Department',
            'color' => '#10b981',
            'can_be_root' => false,
            'parent_type_ids' => [],
        ])
        ->assertForbidden();
});

test('editor role can create, update, and delete areas from edit structure', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $createResponse = $this->actingAs($user)
        ->postJson(route('organization-chart.edit.areas.store'), [
            'code' => 'PAN',
            'name' => 'Panabo Area',
        ])
        ->assertOk()
        ->assertJsonPath('data.code', 'PAN')
        ->assertJsonPath('data.name', 'Panabo Area');

    $areaId = (int) $createResponse->json('data.id');
    /** @var Area $area */
    $area = Area::query()->findOrFail($areaId);

    $this->actingAs($user)
        ->patchJson(route('organization-chart.edit.areas.update', $area), [
            'code' => 'TAG',
            'name' => 'Tagum Area',
        ])
        ->assertOk()
        ->assertJsonPath('data.code', 'TAG')
        ->assertJsonPath('data.name', 'Tagum Area');

    $this->assertDatabaseHas('areas', [
        'id' => $areaId,
        'code' => 'TAG',
        'name' => 'Tagum Area',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('organization-chart.edit.areas.destroy', $area))
        ->assertOk();

    $this->assertDatabaseMissing('areas', [
        'id' => $areaId,
    ]);
});

test('non editor role is forbidden from area edit structure endpoints', function (): void {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();
    /** @var Area $area */
    $area = Area::factory()->create([
        'code' => 'TMP',
        'name' => 'Temp Area',
    ]);

    $this->actingAs($user)
        ->postJson(route('organization-chart.edit.areas.store'), [
            'code' => 'NWA',
            'name' => 'New Area',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('organization-chart.edit.areas.update', $area), [
            'code' => 'NWB',
            'name' => 'New Area B',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('organization-chart.edit.areas.destroy', $area))
        ->assertForbidden();
});
