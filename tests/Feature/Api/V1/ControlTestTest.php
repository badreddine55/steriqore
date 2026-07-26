<?php

use Illuminate\Support\Str;

function sterilizationRunningCycleFor(string $token, string $deviceId): string
{
    $cycleId = test()->withToken($token)->postJson('/api/v1/cycles', [
        'device_id' => $deviceId,
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'Item',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/complete", [], ['Idempotency-Key' => (string) Str::uuid()]);

    return $cycleId;
}

test('a control test can be recorded on a completed cycle and listed', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = sterilizationRunningCycleFor($token, $deviceId);

    $create = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/control-tests", [
        'type' => 'bowie_dick', 'result' => 'pass', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()->assertJsonPath('type', 'bowie_dick')->assertJsonPath('result', 'pass');

    $index = $this->withToken($token)->getJson("/api/v1/cycles/{$cycleId}/control-tests");
    $index->assertOk();
    expect($index->json())->toHaveCount(1);
});

test('a control test cannot be recorded before a cycle has completed running', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = $this->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $response = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/control-tests", [
        'type' => 'vacuum', 'result' => 'pass', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'CYCLE_NOT_READY_FOR_TESTS');
});

test('a control test can still be recorded after a cycle is submitted for release (late biological result)', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = sterilizationRunningCycleFor($token, $deviceId);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $response = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/control-tests", [
        'type' => 'biological', 'result' => 'pass', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertCreated();
});

test('a failed control test opens a failed_cycle alert', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = sterilizationRunningCycleFor($token, $deviceId);

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/control-tests", [
        'type' => 'helix', 'result' => 'fail', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $alerts = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=failed_cycle');
    $alerts->assertOk();
    expect($alerts->json('data'))->toHaveCount(1);
    expect($alerts->json('data.0.subject_id'))->toBe($cycleId);
});

test('recording a passing test does not open a failed_cycle alert', function () {
    [$tenant, $user] = sterilizationTenantWithMember();
    $token = sterilizationTokenFor($tenant, $user);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = sterilizationRunningCycleFor($token, $deviceId);

    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/control-tests", [
        'type' => 'helix', 'result' => 'pass', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $alerts = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=failed_cycle');
    expect($alerts->json('data'))->toBeEmpty();
});

test('a practice never reaches another practice cycle control tests, even by guessing a cycle id (IDOR)', function () {
    [$tenantA, $userA] = sterilizationTenantWithMember();
    [$tenantB, $userB] = sterilizationTenantWithMember();
    $tokenA = sterilizationTokenFor($tenantA, $userA);
    $tokenB = sterilizationTokenFor($tenantB, $userB);
    $deviceBId = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleBId = sterilizationRunningCycleFor($tokenB, $deviceBId);

    $this->withToken($tokenA)->getJson("/api/v1/cycles/{$cycleBId}/control-tests")->assertStatus(404);
    $this->withToken($tokenA)->postJson("/api/v1/cycles/{$cycleBId}/control-tests", [
        'type' => 'vacuum', 'result' => 'pass', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(404);
});
