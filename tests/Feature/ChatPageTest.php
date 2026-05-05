<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('authenticated users can visit the chat page', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chat'));
});
