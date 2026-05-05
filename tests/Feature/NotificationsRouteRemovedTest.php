<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('notifications route is not registered', function () {
    expect(Route::has('notifications'))->toBeFalse();
});

test('get notifications url returns 404 for authenticated users', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/notifications')->assertNotFound();
});
