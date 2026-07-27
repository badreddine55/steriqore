<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner sees a recorded test in the tenant-wide logbook', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);
    addCycleItem($token, $cycleId, 'Scaler kit, pouch 1');
    recordControlTest($token, $cycleId);

    $this->actingAs($owner)
        ->get('/control-tests')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sterilization/control-tests/index')
            ->has('tests.data', 1)
            ->where('tests.data.0.cycle_id', $cycleId)
            ->where('tests.data.0.result', 'pass')
            ->where('tests.data.0.type', 'vacuum')
        );
});

test('the logbook can be filtered to a result with no matches', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);
    addCycleItem($token, $cycleId, 'Scaler kit, pouch 1');
    recordControlTest($token, $cycleId);

    $this->actingAs($owner)
        ->get('/control-tests?filter[result]=fail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tests.data', 0));
});

test('a practice cannot see another practice tests in the logbook (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleIdB = cycleFor($tokenB, $deviceIdB);
    addCycleItem($tokenB, $cycleIdB, 'Scaler kit, pouch 1');
    recordControlTest($tokenB, $cycleIdB);

    $this->actingAs($ownerA)
        ->get('/control-tests')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tests.data', 0));
});

test('a user without cycles.view is refused the logbook', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
    });

    $this->actingAs($owner->fresh())->get('/control-tests')->assertForbidden();
});

test('an owner can record a control test once the cycle has completed', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $this->actingAs($owner)->post("/cycles/{$cycleId}/items", ['description' => 'x'])->assertRedirect();
    $this->actingAs($owner)->post("/cycles/{$cycleId}/start")->assertRedirect();
    $this->actingAs($owner)->post("/cycles/{$cycleId}/complete")->assertRedirect();

    $this->actingAs($owner)->post("/cycles/{$cycleId}/control-tests", [
        'type' => 'bowie_dick',
        'result' => 'pass',
        'performed_at' => now()->toIso8601String(),
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('controlTests', 1)
            ->where('controlTests.0.type', 'bowie_dick')
        );
});

test('recording a test on a draft cycle is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    $response = $this->actingAs($owner)->post("/cycles/{$cycleId}/control-tests", [
        'type' => 'vacuum',
        'result' => 'pass',
        'performed_at' => now()->toIso8601String(),
    ]);

    // ApiExceptionWebRenderer converts the domain's CYCLE_NOT_READY_FOR_TESTS
    // into a normal redirect-back, not the API's JSON envelope or a raw 500.
    $response->assertRedirect();
    $response->assertSessionHas('_flash');
});

test('recording a test requires cycles.manage', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = cycleFor($token, $deviceId);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Practitioner->value);
    });

    $this->actingAs($owner->fresh())->post("/cycles/{$cycleId}/control-tests", [
        'type' => 'vacuum',
        'result' => 'pass',
        'performed_at' => now()->toIso8601String(),
    ])->assertForbidden();
});

test('a practice cannot record a test on another practice cycle (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleIdB = cycleFor($tokenB, $deviceIdB);

    $this->actingAs($ownerA)->post("/cycles/{$cycleIdB}/control-tests", [
        'type' => 'vacuum',
        'result' => 'pass',
        'performed_at' => now()->toIso8601String(),
    ])->assertNotFound();
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/control-tests')->assertRedirect('/login');
});
