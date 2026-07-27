<?php

use App\Domain\Equipment\Models\DeviceProgram;
use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Inertia\Testing\AssertableInertia as Assert;

test('the device detail page shows its programs', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $this->actingAs($owner)->post("/devices/{$deviceId}/programs", [
        'name' => '134°C Standard',
        'target_temperature_celsius' => 134,
        'plateau_minutes' => 18,
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/devices/{$deviceId}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs', 1)
            ->where('programs.0.name', '134°C Standard')
            ->where('programs.0.target_temperature_celsius', 134)
            ->where('programs.0.is_active', true)
        );
});

test('a program can be updated and removed', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $this->actingAs($owner)->post("/devices/{$deviceId}/programs", [
        'name' => '134°C Standard',
        'target_temperature_celsius' => 134,
        'plateau_minutes' => 18,
    ]);
    $programId = DeviceProgram::withoutGlobalScope(TenantScope::class)
        ->where('device_id', $deviceId)->firstOrFail()->id;

    $this->actingAs($owner)->patch("/devices/{$deviceId}/programs/{$programId}", [
        'is_active' => false,
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/devices/{$deviceId}")
        ->assertInertia(fn (Assert $page) => $page->where('programs.0.is_active', false));

    $this->actingAs($owner)->delete("/devices/{$deviceId}/programs/{$programId}")->assertRedirect();

    $this->actingAs($owner)
        ->get("/devices/{$deviceId}")
        ->assertInertia(fn (Assert $page) => $page->has('programs', 0));
});

test('a viewer cannot add a program', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())->post("/devices/{$deviceId}/programs", [
        'name' => '134°C Standard',
        'target_temperature_celsius' => 134,
        'plateau_minutes' => 18,
    ])->assertForbidden();
});

test('a practice cannot add a program to another practice device (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);

    $this->actingAs($ownerA)->post("/devices/{$deviceIdB}/programs", [
        'name' => '134°C Standard',
        'target_temperature_celsius' => 134,
        'plateau_minutes' => 18,
    ])->assertNotFound();
});
