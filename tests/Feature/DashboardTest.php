<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('an authenticated user with an active practice membership can visit the dashboard', function () {
    [, $user] = sterilizationTenantWithMember(TenantRole::Owner);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('an authenticated user with no active practice membership anywhere is refused', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertForbidden();
});
