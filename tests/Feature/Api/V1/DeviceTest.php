<?php

use App\Domain\Equipment\Models\Device;
use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function equipmentTenantWithMember(TenantRole $role = TenantRole::StockManager): array
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

function equipmentTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

function equipmentSiteFor(Tenant $tenant): string
{
    return $tenant->sites()->create(['name' => 'Cabinet Principal'])->id;
}

test('a stock manager can create, read, update, and delete a device', function () {
    [$tenant, $user] = equipmentTenantWithMember();
    $token = equipmentTokenFor($tenant, $user);
    $siteId = equipmentSiteFor($tenant);

    $create = $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-001',
        'manufacturer' => 'Melag', 'model' => 'Vacuklav 44B',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()
        ->assertJsonPath('name', 'Autoclave 1')
        ->assertJsonPath('serial_number', 'SN-001')
        ->assertJsonPath('kind', 'autoclave')
        ->assertJsonPath('status', 'active');
    $deviceId = $create->json('id');

    $this->withToken($token)->getJson("/api/v1/devices/{$deviceId}")
        ->assertOk()->assertJsonPath('manufacturer', 'Melag');

    $this->withToken($token)->patchJson("/api/v1/devices/{$deviceId}", ['status' => 'maintenance'])
        ->assertOk()->assertJsonPath('status', 'maintenance');

    $this->withToken($token)->deleteJson("/api/v1/devices/{$deviceId}")->assertNoContent();
    expect(
        Device::withoutGlobalScope(TenantScope::class)->withTrashed()->find($deviceId)->trashed()
    )->toBeTrue();
});

test('creating a device with a duplicate serial number within the same tenant is rejected', function () {
    [$tenant, $user] = equipmentTenantWithMember();
    $token = equipmentTokenFor($tenant, $user);
    $siteId = equipmentSiteFor($tenant);

    $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 1', 'serial_number' => 'SN-DUP',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $response = $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'Autoclave 2', 'serial_number' => 'SN-DUP',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'DEVICE_SERIAL_NUMBER_TAKEN');
});

test('two different tenants can each use the same serial number (unique per tenant, not globally)', function () {
    [$tenantA, $userA] = equipmentTenantWithMember();
    [$tenantB, $userB] = equipmentTenantWithMember();
    $tokenA = equipmentTokenFor($tenantA, $userA);
    $tokenB = equipmentTokenFor($tenantB, $userB);

    $this->withToken($tokenA)->postJson('/api/v1/devices', [
        'site_id' => equipmentSiteFor($tenantA), 'name' => 'Autoclave 1', 'serial_number' => 'SN-SHARED',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->withToken($tokenB)->postJson('/api/v1/devices', [
        'site_id' => equipmentSiteFor($tenantB), 'name' => 'Autoclave 1', 'serial_number' => 'SN-SHARED',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
});

test('a viewer can read devices but cannot create one (vertical escalation)', function () {
    [$tenant, $user] = equipmentTenantWithMember(TenantRole::Viewer);
    $token = equipmentTokenFor($tenant, $user);
    $siteId = equipmentSiteFor($tenant);

    $this->withToken($token)->getJson('/api/v1/devices')->assertOk();
    $this->withToken($token)->postJson('/api/v1/devices', [
        'site_id' => $siteId, 'name' => 'X', 'serial_number' => 'SN-X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice device, even by guessing an id (IDOR)', function () {
    [$tenantA, $userA] = equipmentTenantWithMember();
    [$tenantB, $userB] = equipmentTenantWithMember();
    $tokenA = equipmentTokenFor($tenantA, $userA);
    $tokenB = equipmentTokenFor($tenantB, $userB);

    $foreignDeviceId = $this->withToken($tokenB)->postJson('/api/v1/devices', [
        'site_id' => equipmentSiteFor($tenantB), 'name' => 'Secret Autoclave', 'serial_number' => 'SN-SECRET',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/devices/{$foreignDeviceId}")->assertStatus(404);
});

test('a device cannot be created against a site belonging to another tenant', function () {
    [$tenantA, $userA] = equipmentTenantWithMember();
    [$tenantB, $userB] = equipmentTenantWithMember();
    $tokenA = equipmentTokenFor($tenantA, $userA);
    $foreignSiteId = equipmentSiteFor($tenantB);

    $this->withToken($tokenA)->postJson('/api/v1/devices', [
        'site_id' => $foreignSiteId, 'name' => 'X', 'serial_number' => 'SN-Y',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});
