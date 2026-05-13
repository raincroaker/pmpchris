<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('legacy leave and overtime paths redirect to new urls', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/leave/request')
        ->assertRedirect('/leave/my');

    $this->actingAs($user)
        ->get('/leave/my-requests')
        ->assertRedirect('/leave/my');

    $this->actingAs($user)
        ->get('/leave/team-requests')
        ->assertRedirect('/leave/team');

    $this->actingAs($user)
        ->get('/overtime/request')
        ->assertRedirect('/overtime/my');

    $this->actingAs($user)
        ->get('/overtime/my-requests')
        ->assertRedirect('/overtime/my');

    $this->actingAs($user)
        ->get('/overtime/team-requests')
        ->assertRedirect('/overtime/team');
});

test('leave and overtime my pages render; policy index requires management role', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('leave.my'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leave/My')
            ->has('myEmployeeLeaves.data')
            ->has('myLeaveFilters')
            ->has('myLeaveKpis')
            ->where('hasEmployeeRecord', false));

    $this->actingAs($user)
        ->get(route('overtime.my'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overtime/My')
            ->has('myEmployeeOvertimes.data')
            ->has('myOvertimeFilters')
            ->has('myOvertimeKpis')
            ->where('hasEmployeeRecord', false));

    $this->actingAs($user)
        ->get(route('leave.policies'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('overtime.policies'))
        ->assertForbidden();
});

test('leave and overtime policy pages render for users who may view policies', function (): void {
    (new RoleSeeder)->run();
    config(['hris.branch_picker_enabled' => false]);

    Organization::factory()->create([
        'code' => 'PMPC',
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_HR_HEAD);

    $this->actingAs($user)
        ->get(route('leave.policies'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leave/Policies'));

    $this->actingAs($user)
        ->get(route('overtime.policies'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overtime/Policies'));
});

test('employee team leave and overtime routes forbid users without hr team access', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('leave.team'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('overtime.team'))
        ->assertForbidden();
});

test('short leave and overtime urls redirect to my records pages', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/leave')
        ->assertRedirect('/leave/my');

    $this->actingAs($user)
        ->get('/overtime')
        ->assertRedirect('/overtime/my');
});

test('removed request routes are not registered', function (): void {
    expect(Route::has('leave.request'))->toBeFalse();
    expect(Route::has('overtime.request'))->toBeFalse();
    expect(Route::has('attendance.team'))->toBeFalse();
    expect(Route::has('chat'))->toBeFalse();
});
