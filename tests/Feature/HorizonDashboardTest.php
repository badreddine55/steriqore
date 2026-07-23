<?php

use App\Models\User;

test('horizon dashboard is accessible in the local environment', function () {
    $this->app->detectEnvironment(fn (): string => 'local');

    $this->get('/horizon')->assertOk();
});

test('horizon dashboard is forbidden for unauthorized users outside local', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/horizon')
        ->assertForbidden();
});
