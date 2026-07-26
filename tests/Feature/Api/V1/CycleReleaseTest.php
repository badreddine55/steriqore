<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Sterilization\Models\CycleRelease;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function releaserFor(Tenant $tenant): array
{
    $releaser = User::create([
        'name' => 'Releaser', 'email' => 'releaser-'.Str::lower(Str::random(8)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $releaser->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $releaser->assignRole(TenantRole::Releaser->value);
    $registrar->setPermissionsTeamId(null);

    return [$releaser, sterilizationTokenFor($tenant, $releaser)];
}

test('a releaser can release an awaiting cycle as compliant', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $cycleId = sterilizationRunningCycleFor($managerToken, $deviceId);
    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);

    [$releaser, $releaserToken] = releaserFor($tenant);

    $release = $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $release->assertCreated()
        ->assertJsonPath('decision', 'compliant')
        ->assertJsonPath('cycle_id', $cycleId)
        ->assertJsonPath('released_by_user_id', $releaser->id);

    $this->withToken($releaserToken)->getJson("/api/v1/cycles/{$cycleId}")->assertOk()->assertJsonPath('status', 'released');
    $this->withToken($releaserToken)->getJson("/api/v1/cycles/{$cycleId}/release")->assertOk()->assertJsonPath('decision', 'compliant');
});

test('rejecting a cycle requires a reason', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $cycleId = sterilizationRunningCycleFor($managerToken, $deviceId);
    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);
    [, $releaserToken] = releaserFor($tenant);

    $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'rejected',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);

    $reject = $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'rejected', 'reason' => 'Failed Bowie-Dick control test.',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $reject->assertCreated()->assertJsonPath('decision', 'rejected');

    $this->withToken($releaserToken)->getJson("/api/v1/cycles/{$cycleId}")->assertOk()->assertJsonPath('status', 'rejected');
});

test('a cycle can only be released once', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $cycleId = sterilizationRunningCycleFor($managerToken, $deviceId);
    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);
    [, $releaserToken] = releaserFor($tenant);

    $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $second = $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $second->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_INVALID_TRANSITION');
});

test('a cycle cannot be released before it is awaiting release', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $cycleId = $this->withToken($managerToken)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    [, $releaserToken] = releaserFor($tenant);

    $response = $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $response->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_INVALID_TRANSITION');
});

test('a stock manager who runs cycles cannot release them (vertical escalation)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $cycleId = sterilizationRunningCycleFor($managerToken, $deviceId);
    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a cycle_release row is immutable at the database level', function () {
    [$tenant, $manager] = sterilizationTenantWithMember();
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $cycleId = sterilizationRunningCycleFor($managerToken, $deviceId);
    $this->withToken($managerToken)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);
    [, $releaserToken] = releaserFor($tenant);
    $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $release = CycleRelease::withoutGlobalScope(TenantScope::class)->where('cycle_id', $cycleId)->firstOrFail();

    expect(fn () => $release->update(['decision' => 'rejected']))->toThrow(Exception::class);
    expect(fn () => $release->delete())->toThrow(Exception::class);
});

test('a practice never reaches another practice cycle release (IDOR)', function () {
    [$tenantA, $userA] = sterilizationTenantWithMember();
    [$tenantB, $userB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $userA);
    $tokenB = sterilizationTokenFor($tenantB, $userB);
    $deviceBId = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleBId = sterilizationRunningCycleFor($tokenB, $deviceBId);
    $this->withToken($tokenB)->postJson("/api/v1/cycles/{$cycleBId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->withToken($tokenA)->postJson("/api/v1/cycles/{$cycleBId}/release", [
        'decision' => 'compliant',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(404);
});
