<?php

use Illuminate\Support\Str;

test('the public-api rate limiter blocks excessive unauthenticated login attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'tenant_slug' => 'no-such-tenant', 'email' => 'nobody@example.com', 'password' => 'wrong',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(404);
    }

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => 'no-such-tenant', 'email' => 'nobody@example.com', 'password' => 'wrong',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(429);
});

test('the authenticated api rate limiter is a much higher ceiling than the public one', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);

    for ($i = 0; $i < 15; $i++) {
        $this->withToken($token)->getJson('/api/v1/devices')->assertOk();
    }
});
