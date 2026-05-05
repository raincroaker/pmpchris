<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
});

function seedRolesAndPmpcOrganization(): void
{
    (new RoleSeeder)->run();
    Organization::factory()->create([
        'code' => 'PMPC',
        'is_active' => true,
    ]);
}

test('hr manager cannot store leave policies', function (): void {
    seedRolesAndPmpcOrganization();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_HR_MANAGER);

    $this->actingAs($user)
        ->post(route('leave.policies.store'), [
            'code' => 'X1',
            'name' => 'Extra',
            'unit' => 'days',
            'annual_entitlement' => 1,
            'use_accrual' => false,
        ])
        ->assertForbidden();
});

test('hr head can store leave policies', function (): void {
    seedRolesAndPmpcOrganization();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_HR_HEAD);

    $this->actingAs($user)
        ->post(route('leave.policies.store'), [
            'code' => 'X1',
            'name' => 'Extra',
            'unit' => 'days',
            'annual_entitlement' => 1,
            'use_accrual' => false,
        ])
        ->assertRedirect(route('leave.policies'));
});

test('hr manager cannot store overtime policies', function (): void {
    seedRolesAndPmpcOrganization();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_HR_MANAGER);

    $this->actingAs($user)
        ->post(route('overtime.policies.store'), [
            'code' => 'OTX',
            'name' => 'Test OT',
            'context' => 'ordinary_weekday',
            'rate_multiplier' => 1.25,
            'daily_threshold_hours' => 8,
        ])
        ->assertForbidden();
});

test('super admin can store overtime policies', function (): void {
    seedRolesAndPmpcOrganization();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_SUPER_ADMIN);

    $this->actingAs($user)
        ->post(route('overtime.policies.store'), [
            'code' => 'OTX',
            'name' => 'Test OT',
            'context' => 'ordinary_weekday',
            'rate_multiplier' => 1.25,
            'daily_threshold_hours' => 8,
        ])
        ->assertRedirect(route('overtime.policies'));
});
