<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

test('a stock manager can record maintenance on a device and list its history', function () {
    [$tenant, $user] = equipmentTenantWithMember();
    $token = equipmentTokenFor($tenant, $user);
    $siteId = equipmentSiteFor($tenant);
    $deviceId = $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-001',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $create = $this->withToken($token)->postJson("/api/v1/devices/{$deviceId}/maintenance-records", [
        'kind' => 'preventive',
        'technician' => 'ACME Maintenance Co.',
        'performed_at' => now()->toIso8601String(),
        'next_due_at' => now()->addMonths(6)->toIso8601String(),
        'description' => 'Annual seal replacement',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()
        ->assertJsonPath('kind', 'preventive')
        ->assertJsonPath('technician', 'ACME Maintenance Co.');

    $index = $this->withToken($token)->getJson("/api/v1/devices/{$deviceId}/maintenance-records");
    $index->assertOk();
    expect($index->json())->toHaveCount(1);
    expect($index->json('0.recorded_by_user_id'))->toBe($user->id);
});

test('a viewer can read maintenance records but cannot record one (vertical escalation)', function () {
    [$tenant, $manager] = equipmentTenantWithMember();
    $managerToken = equipmentTokenFor($tenant, $manager);
    $siteId = equipmentSiteFor($tenant);
    $deviceId = $this->withToken($managerToken)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-001',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = equipmentTokenFor($tenant, $viewer);

    $this->withToken($viewerToken)->getJson("/api/v1/devices/{$deviceId}/maintenance-records")->assertOk();
    $this->withToken($viewerToken)->postJson("/api/v1/devices/{$deviceId}/maintenance-records", [
        'kind' => 'preventive', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice device maintenance records (IDOR)', function () {
    [$tenantA, $userA] = equipmentTenantWithMember();
    [$tenantB, $userB] = equipmentTenantWithMember();
    $tokenA = equipmentTokenFor($tenantA, $userA);
    $tokenB = equipmentTokenFor($tenantB, $userB);

    $deviceBId = $this->withToken($tokenB)->postJson('/api/v1/devices', [
        'site_id' => equipmentSiteFor($tenantB), 'name' => 'Autoclave B', 'serial_number' => 'SN-B',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/devices/{$deviceBId}/maintenance-records")->assertStatus(404);
    $this->withToken($tokenA)->postJson("/api/v1/devices/{$deviceBId}/maintenance-records", [
        'kind' => 'preventive', 'performed_at' => now()->toIso8601String(),
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(404);
});
