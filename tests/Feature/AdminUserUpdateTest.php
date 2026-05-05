<?php

use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\AdminUserActionAccessService;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('hr head can update employee email role and password', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();
    /** @var User $target */
    $target = User::factory()->withRoles(Role::CODE_EMPLOYEE)->createOne([
        'email' => 'old-employee@example.com',
        'password' => 'password',
    ]);
    $hrHeadRole = Role::query()->where('code', Role::CODE_HR_HEAD)->firstOrFail();

    $this->actingAs($actor)
        ->patch(route('admin.users.update', $target), [
            'email' => 'employee-updated@example.com',
            'role_id' => $hrHeadRole->id,
            'password' => 'UpdatedPass123!',
            'password_confirmation' => 'UpdatedPass123!',
        ])
        ->assertRedirect();

    $target->refresh();

    expect($target->email)->toBe('employee-updated@example.com');
    expect(Hash::check('UpdatedPass123!', (string) $target->password))->toBeTrue();
    expect($target->roles()->pluck('code')->all())->toBe([Role::CODE_HR_HEAD]);
});

test('user cannot change their own role assignment from admin endpoint', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne([
        'email' => 'self-actor@example.com',
    ]);
    $employeeRole = Role::query()->where('code', Role::CODE_EMPLOYEE)->firstOrFail();

    $this->actingAs($actor)
        ->patch(route('admin.users.update', $actor), [
            'email' => 'self-actor@example.com',
            'role_id' => $employeeRole->id,
        ])
        ->assertSessionHasErrors('role_id');
});

test('user can change their own password without role change', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne([
        'email' => 'self-password@example.com',
    ]);

    $this->actingAs($actor)
        ->patch(route('admin.users.update', $actor), [
            'email' => 'self-password@example.com',
            'password' => 'SelfPass123!',
            'password_confirmation' => 'SelfPass123!',
        ])
        ->assertRedirect();

    $actor->refresh();

    expect(Hash::check('SelfPass123!', (string) $actor->password))->toBeTrue();
});

test('hr head can modify hr manager account', function () {
    config(['hris.default_organization_code' => 'T-ADMIN-UPD']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-UPD',
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
        'is_active' => true,
    ]);

    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();
    /** @var User $target */
    $target = User::factory()->withRoles(Role::CODE_HR_MANAGER)->createOne([
        'email' => 'hr-manager@example.com',
    ]);

    $this->actingAs($actor)
        ->patch(route('admin.users.update', $target), [
            'email' => 'hr-manager-updated@example.com',
            'branch_ids' => [$root->id],
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->email)->toBe('hr-manager-updated@example.com');
});

test('branch-assigned hr manager can update email and password only', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-ADMIN-UPD-HRM']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-UPD-HRM',
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
        'code' => 'HRM-UPD-ROOT',
        'name' => 'HRM Update Root',
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
    /** @var User $target */
    $target = User::factory()->withRoles(Role::CODE_EMPLOYEE)->createOne([
        'email' => 'target-hrm@example.com',
        'password' => 'password',
    ]);
    $hrHeadRole = Role::query()->where('code', Role::CODE_HR_HEAD)->firstOrFail();

    $this->actingAs($actor)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $root->code,
                'name' => (string) $root->name,
            ],
        ])
        ->patch(route('admin.users.update', $target), [
            'email' => 'target-updated-hrm@example.com',
            'role_id' => $hrHeadRole->id,
            'password' => 'UpdatedByMgr123!',
            'password_confirmation' => 'UpdatedByMgr123!',
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->email)->toBe('target-updated-hrm@example.com');
    expect(Hash::check('UpdatedByMgr123!', (string) $target->password))->toBeTrue();
    expect($target->roles()->pluck('code')->all())->toBe([Role::CODE_EMPLOYEE]);
});

test('non-super-admin cannot assign super administrator role', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();
    /** @var User $target */
    $target = User::factory()->withRoles(Role::CODE_EMPLOYEE)->createOne([
        'email' => 'elevate-me@example.com',
    ]);

    $superAdminRole = Role::query()->where('code', Role::CODE_SUPER_ADMIN)->firstOrFail();

    $this->actingAs($actor)
        ->patch(route('admin.users.update', $target), [
            'email' => 'elevate-me@example.com',
            'role_id' => $superAdminRole->id,
        ])
        ->assertSessionHasErrors('role_id');
});

test('non-super-admin cannot mutate existing super administrator account', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();
    /** @var User $target */
    $target = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->createOne([
        'email' => 'untouchable-super@example.com',
    ]);

    $this->actingAs($actor)
        ->patch(route('admin.users.update', $target), [
            'email' => 'untouchable-super@example.com',
        ])
        ->assertSessionHasErrors('email');
});

test('action access service protects last top admin downgrade', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();

    $service = app(AdminUserActionAccessService::class);

    $reason = $service->roleChangeBlockedReason($actor, $actor, Role::CODE_EMPLOYEE);

    expect($reason)->not->toBeNull();
});

test('hr head can create account for employee without existing user', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();
    $employee = Employee::factory()->create([
        'first_name' => 'No',
        'last_name' => 'Account',
    ]);
    $employeeRole = Role::query()->where('code', Role::CODE_EMPLOYEE)->firstOrFail();

    $this->actingAs($actor)
        ->patch(route('admin.users.update-no-account', $employee), [
            'email' => 'new-account@example.com',
            'role_id' => $employeeRole->id,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])
        ->assertRedirect();

    $created = User::query()
        ->where('employee_id', $employee->id)
        ->first();

    expect($created)->not->toBeNull();
    expect($created?->email)->toBe('new-account@example.com');
    expect(Hash::check('Password123', (string) $created?->password))->toBeTrue();
});

test('create account for no-account employee enforces minimum password length', function () {
    /** @var User $actor */
    $actor = User::factory()->withRoles(Role::CODE_HR_HEAD)->createOne();
    $employee = Employee::factory()->create();

    $this->actingAs($actor)
        ->patch(route('admin.users.update-no-account', $employee), [
            'email' => 'short-pass@example.com',
            'password' => 'Abc123',
            'password_confirmation' => 'Abc123',
        ])
        ->assertSessionHasErrors('password');
});
