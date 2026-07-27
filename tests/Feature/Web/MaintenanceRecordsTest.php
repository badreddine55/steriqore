<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

test('the device detail page shows its maintenance history', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $this->actingAs($owner)->post("/devices/{$deviceId}/maintenance-records", [
        'kind' => 'preventive',
        'technician' => 'Jane Tech',
        'performed_at' => now()->toIso8601String(),
        'next_due_at' => now()->addMonths(6)->toIso8601String(),
        'description' => 'Annual service.',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/devices/{$deviceId}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('maintenanceRecords', 1)
            ->where('maintenanceRecords.0.kind', 'preventive')
            ->where('maintenanceRecords.0.technician', 'Jane Tech')
            ->where('maintenanceRecords.0.description', 'Annual service.')
        );
});

test('a next-due date before the performed date is a validation error, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $this->actingAs($owner)->post("/devices/{$deviceId}/maintenance-records", [
        'kind' => 'preventive',
        'performed_at' => now()->toIso8601String(),
        'next_due_at' => now()->subDay()->toIso8601String(),
    ])->assertSessionHasErrors('next_due_at');
});

test('a viewer cannot record maintenance', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())->post("/devices/{$deviceId}/maintenance-records", [
        'kind' => 'preventive',
        'performed_at' => now()->toIso8601String(),
    ])->assertForbidden();
});

test('a practice cannot record maintenance for another practice device (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);

    $this->actingAs($ownerA)->post("/devices/{$deviceIdB}/maintenance-records", [
        'kind' => 'preventive',
        'performed_at' => now()->toIso8601String(),
    ])->assertNotFound();
});
