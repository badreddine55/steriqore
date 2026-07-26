<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Sterilization\Models\Cycle;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function sterilizationTenantWithMember(TenantRole $role = TenantRole::StockManager): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);
    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

function sterilizationTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

function sterilizationDeviceFor(string $token, Tenant $tenant): string
{
    $siteId = $tenant->sites()->create(['name' => 'Cabinet Principal'])->id;

    return test()->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-'.Str::random(8),
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
}

test('a stock manager can run a cycle through its full state machine', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $create = $this->withToken($token)->postJson('/api/v1/cycles', [
        'device_id' => $deviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('cycle_number', 1)
        ->assertJsonPath('device_id', $deviceId);
    $cycleId = $create->json('id');

    $item = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'Kit chirurgical implant',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $item->assertCreated()->assertJsonPath('sequence_in_cycle', 1);

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()->assertJsonPath('status', 'running');

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/complete", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()->assertJsonPath('status', 'completed');

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()->assertJsonPath('status', 'awaiting_release');
});

test('a cycle cannot be started with an empty load', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $cycleId = $this->withToken($token)->postJson('/api/v1/cycles', [
        'device_id' => $deviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $response = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $response->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_LOAD_EMPTY');
});

test('cycle state transitions are enforced: cannot complete a draft cycle, cannot start a running one', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $cycleId = $this->withToken($token)->postJson('/api/v1/cycles', [
        'device_id' => $deviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/complete", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_INVALID_TRANSITION');

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'Item',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_INVALID_TRANSITION');
});

test('items cannot be added once a cycle is running', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $cycleId = $this->withToken($token)->postJson('/api/v1/cycles', [
        'device_id' => $deviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'Item 1',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $response = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'Item 2',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $response->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_LOAD_LOCKED');
});

test('cycle numbers are sequential per device and never reused across tenants', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $first = $this->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()]);
    $second = $this->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()]);

    expect($first->json('cycle_number'))->toBe(1);
    expect($second->json('cycle_number'))->toBe(2);
});

test('a cycle cannot be started on a device that is not active', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $this->withToken($token)->patchJson("/api/v1/devices/{$deviceId}", ['status' => 'maintenance']);

    $response = $this->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()]);
    $response->assertStatus(409)->assertJsonPath('error.code', 'DEVICE_NOT_ACTIVE');
});

test('a viewer can read cycles but cannot create one (vertical escalation)', function () {
    [$managerTenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($managerTenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $managerTenant);

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $managerTenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($managerTenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = sterilizationTokenFor($managerTenant, $viewer);

    $this->withToken($viewerToken)->getJson('/api/v1/cycles')->assertOk();
    $this->withToken($viewerToken)->postJson('/api/v1/cycles', [
        'device_id' => $deviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice cycle, even by guessing an id (IDOR)', function () {
    [$tenantA, $userA] = sterilizationTenantWithMember();
    [$tenantB, $userB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $userA);
    $tokenB = sterilizationTokenFor($tenantB, $userB);
    $deviceBId = sterilizationDeviceFor($tokenB, $tenantB);

    $foreignCycleId = $this->withToken($tokenB)->postJson('/api/v1/cycles', [
        'device_id' => $deviceBId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/cycles/{$foreignCycleId}")->assertStatus(404);
    $this->withToken($tokenA)->postJson("/api/v1/cycles/{$foreignCycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(404);
});

test('a cycle cannot be created against a device belonging to another tenant', function () {
    [$tenantA, $userA] = sterilizationTenantWithMember();
    [$tenantB, $userB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $userA);
    $tokenB = sterilizationTokenFor($tenantB, $userB);
    $foreignDeviceId = sterilizationDeviceFor($tokenB, $tenantB);

    $this->withToken($tokenA)->postJson('/api/v1/cycles', [
        'device_id' => $foreignDeviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});

test('an item can be removed from a cycle while still in draft', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = $this->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $itemId = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'Item',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->deleteJson("/api/v1/cycles/{$cycleId}/items/{$itemId}")->assertNoContent();
    $this->withToken($token)->getJson("/api/v1/cycles/{$cycleId}/items")->assertOk()->assertJsonCount(0);
});

test('a viewer can read cycle items but cannot add one (vertical escalation)', function () {
    [$managerTenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($managerTenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $managerTenant);
    $cycleId = $this->withToken($managerToken)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $managerTenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($managerTenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = sterilizationTokenFor($managerTenant, $viewer);

    $this->withToken($viewerToken)->getJson("/api/v1/cycles/{$cycleId}/items")->assertOk();
    $this->withToken($viewerToken)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('cycles is an evidence table: there is no delete route', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = $this->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->deleteJson("/api/v1/cycles/{$cycleId}")->assertStatus(405);
    expect(Cycle::withoutGlobalScope(TenantScope::class)->find($cycleId))->not->toBeNull();
});
