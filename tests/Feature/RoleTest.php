<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('hasRole and hasAnyRole reflect synced role codes', function () {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->create();
    $user->syncRolesByCode([Role::CODE_EMPLOYEE, Role::CODE_HR_MANAGER]);

    expect($user->hasRole(Role::CODE_EMPLOYEE))->toBeTrue()
        ->and($user->hasRole(Role::CODE_HR_MANAGER))->toBeTrue()
        ->and($user->hasRole(Role::CODE_SUPER_ADMIN))->toBeFalse()
        ->and($user->hasAnyRole([Role::CODE_HR_HEAD, Role::CODE_HR_MANAGER]))->toBeTrue()
        ->and($user->hasAnyRole([Role::CODE_HR_HEAD, Role::CODE_SUPER_ADMIN]))->toBeFalse();
});

test('assigning the same role twice does not duplicate pivot rows', function () {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_EMPLOYEE);
    $user->assignRole(Role::CODE_EMPLOYEE);

    expect($user->roles()->count())->toBe(1);
});

test('user factory withRoles attaches expected roles when roles exist', function () {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE, Role::CODE_HR_HEAD)->create();

    expect($user->hasRole(Role::CODE_EMPLOYEE))->toBeTrue()
        ->and($user->hasRole(Role::CODE_HR_HEAD))->toBeTrue()
        ->and($user->roles()->count())->toBe(2);
});

test('role_user enforces unique user_id and role_id', function () {
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->create();
    $roleId = Role::query()->where('code', Role::CODE_EMPLOYEE)->value('id');

    $user->roles()->attach($roleId);

    expect(fn () => $user->roles()->attach($roleId))
        ->toThrow(QueryException::class);
});
