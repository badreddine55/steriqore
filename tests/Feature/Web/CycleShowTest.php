<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function addCycleItem(string $token, string $cycleId, string $description): void
{
    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => $description,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
}

function recordControlTest(string $token, string $cycleId): void
{
    // Control tests can only be recorded once a cycle has completed
    // running (a real, existing business rule — CYCLE_NOT_READY_FOR_TESTS)
    // — advance the state machine for real rather than seeding the row
    // directly, so this fixture can never drift from what the domain
    // actually allows.
    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/complete", [], ['Idempotency-Key' => (string) Str::uuid()]);

    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/control-tests", [
        'type' => 'vacuum',
        'result' => 'pass',
        'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()]);
}

test('an owner can view a cycle detail with its load composition and control tests', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);
    addCycleItem($token, $cycleId, 'Scaler kit, pouch 1');
    recordControlTest($token, $cycleId);

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sterilization/cycles/show')
            ->where('cycle.id', $cycleId)
            ->where('cycle.status', 'completed')
            ->where('device.label', 'Autoclave 1')
            ->has('items', 1)
            ->where('items.0.description', 'Scaler kit, pouch 1')
            ->has('controlTests', 1)
            ->where('controlTests.0.result', 'pass')
            ->where('attachments', [])
            ->where('release', null)
        );
});

test('a practice cannot view another practice cycle detail (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleIdB = cycleFor($tokenB, $deviceIdB);

    $this->actingAs($ownerA)->get("/cycles/{$cycleIdB}")->assertNotFound();
});

test('a user without cycles.view is refused the cycle detail page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
    });

    $this->actingAs($owner->fresh())->get("/cycles/{$cycleId}")->assertForbidden();
});

test('an unauthenticated visitor is redirected to login', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $this->get("/cycles/{$cycleId}")->assertRedirect('/login');
});

test('a nonexistent cycle id returns 404, not a 500', function () {
    [, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->get('/cycles/'.(string) Str::uuid())->assertNotFound();
});
