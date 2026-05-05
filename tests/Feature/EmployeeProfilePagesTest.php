<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from about me', function () {
    $this->get(route('employees.about-me'))->assertRedirect(route('login'));
});

test('guests are redirected from employee show', function () {
    $this->get(route('employees.show', ['employee' => 1]))->assertRedirect(route('login'));
});

test('authenticated users can visit about me', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('employees.about-me'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/AboutMe'));
});

test('authenticated users can visit employee show', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('employees.show', ['employee' => 42]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Show')
            ->where('employeeId', 42));
});
