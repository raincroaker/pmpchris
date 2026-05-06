<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('password reset routes are not registered', function () {
    expect(Route::has('password.request'))->toBeFalse();
    expect(Route::has('password.email'))->toBeFalse();
    expect(Route::has('password.reset'))->toBeFalse();
    expect(Route::has('password.update'))->toBeFalse();
});

test('forgot and reset password endpoints return 404 for guests', function () {
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password', [])->assertNotFound();
    $this->get('/reset-password/example-token')->assertNotFound();
    $this->post('/reset-password', [])->assertNotFound();
});

test('forgot and reset password endpoints return 404 for authenticated users', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $this->actingAs($user);

    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password', [])->assertNotFound();
    $this->get('/reset-password/example-token')->assertNotFound();
    $this->post('/reset-password', [])->assertNotFound();
});
