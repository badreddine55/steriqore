<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

test('a stock manager can create, update, and delete a program on a device', function () {
    [$tenant, $user] = equipmentTenantWithMember();
    $token = equipmentTokenFor($tenant, $user);
    $siteId = equipmentSiteFor($tenant);
    $deviceId = $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-001',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $create = $this->withToken($token)->postJson("/api/v1/devices/{$deviceId}/programs", [
        'name' => 'Universal 134°C', 'target_temperature_celsius' => 134, 'plateau_minutes' => 18,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()->assertJsonPath('name', 'Universal 134°C')->assertJsonPath('is_active', true);
    $programId = $create->json('id');

    $index = $this->withToken($token)->getJson("/api/v1/devices/{$deviceId}/programs");
    $index->assertOk();
    expect(collect($index->json())->pluck('id'))->toContain($programId);

    $this->withToken($token)->patchJson("/api/v1/devices/{$deviceId}/programs/{$programId}", ['plateau_minutes' => 20])
        ->assertOk()->assertJsonPath('plateau_minutes', 20);

    $this->withToken($token)->deleteJson("/api/v1/devices/{$deviceId}/programs/{$programId}")->assertNoContent();
    $this->withToken($token)->getJson("/api/v1/devices/{$deviceId}/programs")
        ->assertOk()
        ->assertJsonCount(0);
});

test('creating a program with a duplicate name on the same device is rejected', function () {
    [$tenant, $user] = equipmentTenantWithMember();
    $token = equipmentTokenFor($tenant, $user);
    $siteId = equipmentSiteFor($tenant);
    $deviceId = $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-001',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->postJson("/api/v1/devices/{$deviceId}/programs", [
        'name' => 'Prion 134°C', 'target_temperature_celsius' => 134, 'plateau_minutes' => 18,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $response = $this->withToken($token)->postJson("/api/v1/devices/{$deviceId}/programs", [
        'name' => 'Prion 134°C', 'target_temperature_celsius' => 134, 'plateau_minutes' => 20,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'DEVICE_PROGRAM_NAME_TAKEN');
});

test('the same program name is allowed on two different devices', function () {
    [$tenant, $user] = equipmentTenantWithMember();
    $token = equipmentTokenFor($tenant, $user);
    $siteId = equipmentSiteFor($tenant);
    $deviceAId = $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave A', 'serial_number' => 'SN-A',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    $deviceBId = $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave B', 'serial_number' => 'SN-B',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->postJson("/api/v1/devices/{$deviceAId}/programs", [
        'name' => 'Universal 134°C', 'target_temperature_celsius' => 134, 'plateau_minutes' => 18,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->withToken($token)->postJson("/api/v1/devices/{$deviceBId}/programs", [
        'name' => 'Universal 134°C', 'target_temperature_celsius' => 134, 'plateau_minutes' => 18,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
});

test('a viewer can read programs but cannot create one (vertical escalation)', function () {
    [$managerTenant, $manager] = equipmentTenantWithMember();
    $managerToken = equipmentTokenFor($managerTenant, $manager);
    $siteId = equipmentSiteFor($managerTenant);
    $deviceId = $this->withToken($managerToken)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-001',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    // The viewer must be a member of the SAME tenant as the device, so this
    // is a permission check, not a tenant-isolation check.
    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $managerTenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($managerTenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = equipmentTokenFor($managerTenant, $viewer);

    $this->withToken($viewerToken)->getJson("/api/v1/devices/{$deviceId}/programs")->assertOk();
    $this->withToken($viewerToken)->postJson("/api/v1/devices/{$deviceId}/programs", [
        'name' => 'X', 'target_temperature_celsius' => 121, 'plateau_minutes' => 20,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice device programs (IDOR)', function () {
    [$tenantA, $userA] = equipmentTenantWithMember();
    [$tenantB, $userB] = equipmentTenantWithMember();
    $tokenA = equipmentTokenFor($tenantA, $userA);
    $tokenB = equipmentTokenFor($tenantB, $userB);

    $deviceBId = $this->withToken($tokenB)->postJson('/api/v1/devices', [
        'site_id' => equipmentSiteFor($tenantB), 'name' => 'Autoclave B', 'serial_number' => 'SN-B',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/devices/{$deviceBId}/programs")->assertStatus(404);
    $this->withToken($tokenA)->postJson("/api/v1/devices/{$deviceBId}/programs", [
        'name' => 'X', 'target_temperature_celsius' => 121, 'plateau_minutes' => 20,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(404);
});
