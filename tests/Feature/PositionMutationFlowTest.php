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

test('guests cannot mutate positions', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-MUT-GUEST',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->postJson(route('positions.store'), [
        'code' => 'NEW-POS',
        'title' => 'New Position',
    ])->assertUnauthorized();

    $this->patchJson(route('positions.update', $position), [
        'code' => 'UPDATED-POS',
        'title' => 'Updated Position',
    ])->assertUnauthorized();

    $this->patchJson(route('positions.deactivate', $position))
        ->assertUnauthorized();
});

test('employee role cannot mutate positions', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-MUT-EMP',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->postJson(route('positions.store'), [
            'code' => 'NEW-POS',
            'title' => 'New Position',
        ])->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('positions.update', $position), [
            'code' => 'UPDATED-POS',
            'title' => 'Updated Position',
        ])->assertForbidden();

    $this->actingAs($user)
        ->patchJson(route('positions.deactivate', $position))
        ->assertForbidden();
});

test('privileged user can create position in default organization', function (): void {
    config(['hris.default_organization_code' => 'T-POS-CREATE']);
    $organization = Organization::factory()->create([
        'code' => 'T-POS-CREATE',
        'is_active' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->postJson(route('positions.store'), [
            'code' => 'hr-mgr',
            'title' => 'HR Manager',
            'description' => 'Leads human resources.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'HR-MGR')
        ->assertJsonPath('data.title', 'HR Manager')
        ->assertJsonPath('data.is_active', true);

    expect(Position::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'HR-MGR')
        ->exists())->toBeTrue();
});

test('create position rejects duplicate code case-insensitively', function (): void {
    config(['hris.default_organization_code' => 'T-POS-DUP']);
    $organization = Organization::factory()->create([
        'code' => 'T-POS-DUP',
        'is_active' => true,
    ]);
    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'HR-MGR',
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->postJson(route('positions.store'), [
            'code' => 'hr-mgr',
            'title' => 'Duplicate Position',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('privileged user can update position in default organization', function (): void {
    config(['hris.default_organization_code' => 'T-POS-UPD']);
    $organization = Organization::factory()->create([
        'code' => 'T-POS-UPD',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Old Title',
        'description' => 'Old description',
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patchJson(route('positions.update', $position), [
            'code' => 'new-title',
            'title' => 'New Title',
            'description' => 'Updated description',
        ])
        ->assertOk()
        ->assertJsonPath('data.code', 'NEW-TITLE')
        ->assertJsonPath('data.title', 'New Title')
        ->assertJsonPath('data.description', 'Updated description');

    $position->refresh();
    expect($position->code)->toBe('NEW-TITLE')
        ->and($position->title)->toBe('New Title')
        ->and($position->description)->toBe('Updated description');
});

test('update position outside default organization returns not found', function (): void {
    config(['hris.default_organization_code' => 'T-POS-UPD-DEF']);
    Organization::factory()->create([
        'code' => 'T-POS-UPD-DEF',
        'is_active' => true,
    ]);
    $otherOrganization = Organization::factory()->create([
        'code' => 'T-POS-UPD-OTHER',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patchJson(route('positions.update', $position), [
            'code' => 'OUTSIDE-SCOPE',
            'title' => 'Outside Scope',
        ])
        ->assertNotFound();
});

test('update position rejects duplicate code case-insensitively', function (): void {
    config(['hris.default_organization_code' => 'T-POS-UPD-DUP']);
    $organization = Organization::factory()->create([
        'code' => 'T-POS-UPD-DUP',
        'is_active' => true,
    ]);
    $first = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'HR-MGR',
    ]);
    $second = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'HR-ASSIST',
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patchJson(route('positions.update', $second), [
            'code' => 'hr-mgr',
            'title' => 'Assistant Updated',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    expect($first->refresh()->code)->toBe('HR-MGR')
        ->and($second->refresh()->code)->toBe('HR-ASSIST');
});

test('privileged user can deactivate active position in default organization', function (): void {
    config(['hris.default_organization_code' => 'T-POS-DEACT']);
    $organization = Organization::factory()->create([
        'code' => 'T-POS-DEACT',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patchJson(route('positions.deactivate', $position))
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $position->refresh();
    expect($position->is_active)->toBeFalse();
});

test('deactivate position outside default organization returns not found', function (): void {
    config(['hris.default_organization_code' => 'T-POS-DEACT-DEF']);
    Organization::factory()->create([
        'code' => 'T-POS-DEACT-DEF',
        'is_active' => true,
    ]);
    $otherOrganization = Organization::factory()->create([
        'code' => 'T-POS-DEACT-OTHER',
        'is_active' => true,
    ]);
    $position = Position::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patchJson(route('positions.deactivate', $position))
        ->assertNotFound();
});
