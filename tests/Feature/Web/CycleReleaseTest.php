<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

function advanceCycleToAwaitingRelease(mixed $owner, string $cycleId): void
{
    test()->actingAs($owner)->post("/cycles/{$cycleId}/items", ['description' => 'Scaler kit'])->assertRedirect();
    test()->actingAs($owner)->post("/cycles/{$cycleId}/start")->assertRedirect();
    test()->actingAs($owner)->post("/cycles/{$cycleId}/complete")->assertRedirect();
    test()->actingAs($owner)->post("/cycles/{$cycleId}/submit-for-release")->assertRedirect();
}

test('an owner can release a cycle as compliant', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);
    advanceCycleToAwaitingRelease($owner, $cycleId);

    $this->actingAs($owner)->post("/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('cycle.status', 'released')
            ->where('release.decision', 'compliant')
            ->where('release.reason', null)
        );
});

test('an owner can reject a cycle with a reason', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);
    advanceCycleToAwaitingRelease($owner, $cycleId);

    $this->actingAs($owner)->post("/cycles/{$cycleId}/release", [
        'decision' => 'rejected',
        'reason' => 'Bowie-Dick test failed.',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('cycle.status', 'rejected')
            ->where('release.decision', 'rejected')
            ->where('release.reason', 'Bowie-Dick test failed.')
        );
});

test('rejecting a cycle without a reason is a validation error', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);
    advanceCycleToAwaitingRelease($owner, $cycleId);

    $this->actingAs($owner)->post("/cycles/{$cycleId}/release", [
        'decision' => 'rejected',
    ])->assertSessionHasErrors('reason');
});

test('a stock manager cannot release a cycle', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);
    advanceCycleToAwaitingRelease($owner, $cycleId);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::StockManager->value);
    });

    $this->actingAs($owner->fresh())->post("/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ])->assertForbidden();
});

test('releasing a cycle that is not awaiting release is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $response = $this->actingAs($owner)->post("/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('_flash');

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page->where('cycle.status', 'draft'));
});

test('a practice cannot release another practice cycle (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleIdB = cycleFor($tokenB, $deviceIdB);
    advanceCycleToAwaitingRelease($ownerB, $cycleIdB);

    $this->actingAs($ownerA)->post("/cycles/{$cycleIdB}/release", [
        'decision' => 'compliant',
    ])->assertNotFound();
});
