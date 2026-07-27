<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Reuses alertLocationFor/receiveProductWithThreshold
 * (tests/Feature/Api/V1/AlertTest.php) — the real DetectAlertsAction flow
 * that actually opens alerts, rather than inserting Alert rows directly.
 */
test('an owner sees a detected low_stock alert in the inbox', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get('/alerts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/alerts/index')
            ->has('alerts.data', 1)
            ->where('alerts.data.0.type', 'low_stock')
            ->where('alerts.data.0.state', 'open')
            ->where('canManage', true)
        );
});

test('alerts can be filtered by type and state', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    receiveProductWithThreshold($token, $locationId, minThreshold: 0, qty: 10, expiryDate: now()->subDay()->toDateString());
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get('/alerts?filter[type]=expired')
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts.data', 1)
            ->where('alerts.data.0.type', 'expired')
        );

    $this->actingAs($owner)
        ->get('/alerts?filter[state]=resolved')
        ->assertInertia(fn (Assert $page) => $page->has('alerts.data', 0));
});

test('an owner can resolve an open alert, and it stays resolved with the resolver recorded', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $alertId = $this->withToken($token)->getJson('/api/v1/alerts')->json('data.0.id');

    $this->actingAs($owner)
        ->post("/alerts/{$alertId}/resolve")
        ->assertRedirect();

    $this->actingAs($owner)
        ->get('/alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.data.0.state', 'resolved')
            ->where('alerts.data.0.resolved_by_name', 'Alice Dupont')
        );
});

test('resolving an already-resolved alert is a friendly redirect, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $alertId = $this->withToken($token)->getJson('/api/v1/alerts')->json('data.0.id');

    $this->actingAs($owner)->post("/alerts/{$alertId}/resolve");

    $response = $this->actingAs($owner)->post("/alerts/{$alertId}/resolve");
    $response->assertRedirect();
    $response->assertSessionHas('_flash');
});

test('a viewer can see alerts but the resolve button state is hidden (canManage false)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())
        ->get('/alerts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

test('a viewer cannot resolve an alert directly (server-side, not just a hidden button)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $alertId = $this->withToken($token)->getJson('/api/v1/alerts')->json('data.0.id');

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())
        ->post("/alerts/{$alertId}/resolve")
        ->assertForbidden();
});

test('a practice cannot see or resolve another practice alerts (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $locationIdB = alertLocationFor($tenantB);
    receiveProductWithThreshold($tokenB, $locationIdB, minThreshold: 50, qty: 10);
    $this->withToken($tokenB)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $foreignAlertId = $this->withToken($tokenB)->getJson('/api/v1/alerts')->json('data.0.id');

    $this->actingAs($ownerA)
        ->get('/alerts')
        ->assertInertia(fn (Assert $page) => $page->has('alerts.data', 0));

    $this->actingAs($ownerA)
        ->post("/alerts/{$foreignAlertId}/resolve")
        ->assertNotFound();
});
