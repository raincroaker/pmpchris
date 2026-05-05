<?php

use App\Models\Role;
use App\Models\User;
use App\Models\BranchManager;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('employee role cannot use admin email availability endpoint', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_EMPLOYEE)->createOne();

    $this->actingAs($actor)
        ->get(route('admin.users.check-availability', ['email' => 'user@example.com']))
        ->assertForbidden();
});

test('admin email availability endpoint returns expected states', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();
    /** @var User $taken */
    $taken = User::factory()->createOne([
        'email' => 'taken-user@example.com',
    ]);

    $this->actingAs($actor)
        ->get(route('admin.users.check-availability', ['email' => '']))
        ->assertSuccessful()
        ->assertJsonPath('email.status', 'idle');

    $this->actingAs($actor)
        ->get(route('admin.users.check-availability', ['email' => 'invalid-email']))
        ->assertSuccessful()
        ->assertJsonPath('email.status', 'invalid');

    $this->actingAs($actor)
        ->get(route('admin.users.check-availability', ['email' => 'taken-user@example.com']))
        ->assertSuccessful()
        ->assertJsonPath('email.status', 'taken');

    $this->actingAs($actor)
        ->get(route('admin.users.check-availability', [
            'email' => 'taken-user@example.com',
            'ignore_user_id' => $taken->id,
        ]))
        ->assertSuccessful()
        ->assertJsonPath('email.status', 'available');

    $this->actingAs($actor)
        ->get(route('admin.users.check-availability', ['email' => 'fresh-user@example.com']))
        ->assertSuccessful()
        ->assertJsonPath('email.status', 'available');
});

test('branch-assigned hr manager can use admin email availability endpoint', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-ADMIN-AVAIL']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-AVAIL',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'AVAIL-ROOT',
        'name' => 'Availability Root',
        'is_active' => true,
    ]);

    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_MANAGER)->createOne();
    BranchManager::factory()->create([
        'user_id' => $actor->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
    ]);

    $this->actingAs($actor)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $root->code,
                'name' => (string) $root->name,
            ],
        ])
        ->get(route('admin.users.check-availability', ['email' => 'fresh-hrm@example.com']))
        ->assertSuccessful()
        ->assertJsonPath('email.status', 'available');
});

test('unassigned hr manager cannot use admin email availability endpoint for selected branch', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-ADMIN-AVAIL-DENY']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-AVAIL-DENY',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'AVAIL-DENY',
        'name' => 'Availability Deny Root',
        'is_active' => true,
    ]);

    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_MANAGER)->createOne();

    $this->actingAs($actor)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $root->code,
                'name' => (string) $root->name,
            ],
        ])
        ->get(route('admin.users.check-availability', ['email' => 'fresh-hrm@example.com']))
        ->assertRedirect(route('branch.select'));
});
