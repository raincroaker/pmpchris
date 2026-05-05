<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mustSelectBranch is false when branch picker is disabled', function () {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    expect($user->mustSelectBranch())->toBeFalse();
});

test('mustSelectBranch is true for hr_head when picker is enabled', function () {
    config(['hris.branch_picker_enabled' => true]);
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    expect($user->mustSelectBranch())->toBeTrue();
});

test('mustSelectBranch is true for super_admin when picker is enabled', function () {
    config(['hris.branch_picker_enabled' => true]);
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    expect($user->mustSelectBranch())->toBeTrue();
});

test('mustSelectBranch is false for user without picker roles', function () {
    config(['hris.branch_picker_enabled' => true]);
    (new RoleSeeder)->run();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    expect($user->mustSelectBranch())->toBeFalse();
});
