<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from calendar pages', function () {
    $this->get(route('calendar.company'))->assertRedirect(route('login'));
    $this->get(route('calendar.branch'))->assertRedirect(route('login'));
    $this->get(route('calendar.team'))->assertRedirect(route('login'));
    $this->get(route('calendar.holidays'))->assertRedirect(route('login'));
});

test('authenticated users can visit company, branch, and team calendar pages', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('calendar.company'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Calendar/Company'));

    $this->actingAs($user)
        ->get(route('calendar.branch'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Calendar/Branch'));

    $this->actingAs($user)
        ->get(route('calendar.team'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Calendar/Team'));

    $this->actingAs($user)
        ->get(route('calendar.holidays'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Calendar/Holidays'));
});
