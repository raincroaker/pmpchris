<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration routes are not registered', function () {
    expect(Route::has('register'))->toBeFalse();
    expect(Route::has('register.store'))->toBeFalse();
});

test('register endpoints return 404 for guests', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('register endpoints return 404 for authenticated users', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $this->actingAs($user);

    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
