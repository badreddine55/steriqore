<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can view the devices list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    sterilizationDeviceFor($token, $tenant);

    $this->actingAs($owner)
        ->get('/devices')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/devices/index')
            ->has('devices.data', 1)
            ->where('devices.data.0.name', 'Autoclave 1')
            ->where('canCreate', true)
        );
});

test('the devices list can be filtered by name to no matches', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    sterilizationDeviceFor($token, $tenant);

    $this->actingAs($owner)
        ->get('/devices?filter[name]=Nonexistent')
        ->assertInertia(fn (Assert $page) => $page->has('devices.data', 0));
});

test('a device can be created from the web devices page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $siteId = TenantContext::run($tenant, fn () => $tenant->sites()->create(['name' => 'Cabinet Principal'])->id);

    $response = $this->actingAs($owner)->post('/devices', [
        'site_id' => $siteId,
        'name' => 'Autoclave 2',
        'serial_number' => 'SN-'.Str::random(8),
        'kind' => 'autoclave',
    ]);

    $response->assertRedirect();

    $this->actingAs($owner)
        ->get('/devices')
        ->assertInertia(fn (Assert $page) => $page
            ->has('devices.data', 1)
            ->where('devices.data.0.name', 'Autoclave 2')
        );
});

test('creating a device with a duplicate serial number is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $siteId = TenantContext::run($tenant, fn () => $tenant->sites()->create(['name' => 'Cabinet Principal'])->id);

    $first = $this->actingAs($owner)->post('/devices', [
        'site_id' => $siteId,
        'name' => 'Autoclave A',
        'serial_number' => 'SN-DUP',
    ]);
    $first->assertRedirect();

    // ApiExceptionWebRenderer converts DEVICE_SERIAL_NUMBER_TAKEN into a
    // normal redirect-back, not the API's JSON envelope or a raw 500.
    $second = $this->actingAs($owner)->post('/devices', [
        'site_id' => $siteId,
        'name' => 'Autoclave B',
        'serial_number' => 'SN-DUP',
    ]);
    $second->assertRedirect();
    $second->assertSessionHas('_flash');
});

test('a device can be viewed and edited', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $this->actingAs($owner)
        ->get("/devices/{$deviceId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/devices/show')
            ->where('device.id', $deviceId)
            ->where('device.name', 'Autoclave 1')
            ->where('canManage', true)
        );

    $this->actingAs($owner)->patch("/devices/{$deviceId}", [
        'status' => 'maintenance',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/devices/{$deviceId}")
        ->assertInertia(fn (Assert $page) => $page->where('device.status', 'maintenance'));
});

test('a viewer can view but not edit a device', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())
        ->get("/devices/{$deviceId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($owner->fresh())
        ->patch("/devices/{$deviceId}", ['status' => 'maintenance'])
        ->assertForbidden();
});

test('a user without devices.manage cannot create a device', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $siteId = TenantContext::run($tenant, fn () => $tenant->sites()->create(['name' => 'Cabinet Principal'])->id);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())->post('/devices', [
        'site_id' => $siteId,
        'name' => 'Autoclave X',
        'serial_number' => 'SN-X',
    ])->assertForbidden();
});

test('a practice never sees another practice devices in the list (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    sterilizationDeviceFor($tokenB, $tenantB);

    $this->actingAs($ownerA)
        ->get('/devices')
        ->assertInertia(fn (Assert $page) => $page->has('devices.data', 0));
});

test('a practice cannot view another practice device detail (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);

    $this->actingAs($ownerA)->get("/devices/{$deviceIdB}")->assertNotFound();
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/devices')->assertRedirect('/login');
});
