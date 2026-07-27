<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function cycleFor(string $token, string $deviceId): string
{
    return test()->withToken($token)->postJson('/api/v1/cycles', [
        'device_id' => $deviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
}

test('an owner can view the cycles list, scoped to their own tenant', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    cycleFor($token, $deviceId);

    $this->actingAs($owner)
        ->get('/cycles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sterilization/cycles/index')
            ->where('tenant.id', $tenant->id)
            ->where('auth.role', 'owner')
            ->has('auth.permissions')
            ->has('cycles.data', 1)
            ->where('cycles.data.0.status', 'draft')
            ->has('devices', 1)
        );
});

test('a status filter narrows the list, using the filter[...] query bucket', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    cycleFor($token, $deviceId);

    $this->actingAs($owner)
        ->get('/cycles?'.http_build_query(['filter' => ['status' => 'running']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sterilization/cycles/index')
            ->has('cycles.data', 0)
            ->where('filters.status', 'running')
        );

    $this->actingAs($owner)
        ->get('/cycles?'.http_build_query(['filter' => ['status' => 'draft']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cycles.data', 1)
        );
});

test('a practice never sees another practice cycles on the web cycles list (IDOR)', function () {
    [$tenantA, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    cycleFor($tokenB, $deviceIdB);

    $this->actingAs($ownerA)
        ->get('/cycles')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.id', $tenantA->id)
            ->has('cycles.data', 0)
        );
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/cycles')->assertRedirect('/login');
});

test('a user with no active practice membership anywhere is refused, not shown an empty tenant', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/cycles')->assertForbidden();
});

test('a user without the cycles.view permission is refused by the Policy, not just hidden from the nav', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    // cycles.view comes from the 'owner' role, not a direct grant — remove
    // the role itself (rather than revokePermissionTo(), which only ever
    // touches direct user-level grants) to prove the Policy check is real
    // and not just a decorative call that always passes for this fixture.
    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
    });

    $this->actingAs($owner->fresh())->get('/cycles')->assertForbidden();
});
