<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('notifications route is not registered', function () {
    expect(Route::has('notifications'))->toBeFalse();
});

test('get notifications url returns 404 for authenticated users', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/notifications')->assertNotFound();
});
