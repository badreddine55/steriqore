<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Reuses auditTenantWithMember (tests/Feature/Compliance/AuditEventTest.php)
 * and fullEvidenceChainFor (tests/Feature/Api/V1/EvidenceSearchTest.php) —
 * same AuditEvent model/AuditEventData the mobile API uses.
 */
test('an owner sees a login audit event and can filter by action and actor', function () {
    [$tenant, $owner] = auditTenantWithMember(TenantRole::Owner);

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $owner->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get('/audit-log')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compliance/audit-log/index')
            ->has('events.data', 1)
            ->where('events.data.0.action', 'auth.login_succeeded')
            ->where('events.data.0.actor_id', $owner->id)
        );

    $this->actingAs($owner)
        ->get('/audit-log?filter[action]=auth.login_succeeded')
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1));

    $this->actingAs($owner)
        ->get('/audit-log?filter[actor_id]='.$owner->id)
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1));

    $this->actingAs($owner)
        ->get('/audit-log?filter[action]=nothing.matches')
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
});

test('an update carries both old and new values for diffing', function () {
    [$tenant, $owner] = auditTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);

    $this->withToken($token)->patchJson("/api/v1/devices/{$deviceId}", [
        'name' => 'Renamed autoclave',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get('/audit-log?filter[action]=device.updated')
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.new_values.name', 'Renamed autoclave')
        );
});

test('a viewer cannot see the audit log (audit.view is owner/admin only)', function () {
    [$tenant, $owner] = auditTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())->get('/audit-log')->assertForbidden();
});

test('a practice never sees another practice audit events (IDOR)', function () {
    [, $ownerA] = auditTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = auditTenantWithMember(TenantRole::Owner);

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenantB->slug,
        'email' => $ownerB->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($ownerA)
        ->get('/audit-log')
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
});
