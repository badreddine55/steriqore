<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Scans the label (Printed -> Used), then records its usage — the last
 * link of "pouch -> cycle -> device -> practitioner -> procedure ->
 * pseudonymized patient" (CLAUDE.md). This is normally the mobile scan-at-
 * chair flow (RecordLabelUsageAction), not something the web UI triggers;
 * driven via the real API here purely as a fixture so the web label detail
 * page's *display* of that data can be tested honestly.
 */
function useDemoLabel(string $token, Tenant $tenant, User $owner, string $labelId): void
{
    test()->withToken($token)->getJson("/api/v1/labels/{$labelId}")->assertOk();

    $patientId = test()->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->json('id');

    test()->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId,
        'practitioner_id' => $owner->id,
        'procedure' => 'Scaling and root planing',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
}

test('the labels index shows a generated label', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token, 'Sachet', 'Armoire', 180);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($owner)
        ->get('/labels')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sterilization/labels/index')
            ->has('labels.data', 1)
            ->where('labels.data.0.cycle_number', 1)
            ->where('labels.data.0.status', 'created')
        );
});

test('the labels index can be filtered to a status with no matches', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token, 'Sachet', 'Armoire', 180);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($owner)
        ->get('/labels?filter[status]=recalled')
        ->assertInertia(fn (Assert $page) => $page->has('labels.data', 0));
});

test('a practice never sees another practice labels in the index (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    [$cycleIdB] = releasedCompliantCycle($tokenB, $tenantB, $deviceIdB);
    dluRuleFor($tokenB);
    $this->withToken($tokenB)->postJson("/api/v1/cycles/{$cycleIdB}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($ownerA)
        ->get('/labels')
        ->assertInertia(fn (Assert $page) => $page->has('labels.data', 0));
});

test('a user without labels.view is refused the labels index', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
    });

    $this->actingAs($owner->fresh())->get('/labels')->assertForbidden();
});

test('the label detail page shows the chain and print history, with no usage yet', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token, 'Sachet', 'Armoire', 180);
    $labelId = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get("/labels/{$labelId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sterilization/labels/show')
            ->where('label.id', $labelId)
            ->where('cycle.cycle_number', 1)
            ->has('prints', 1)
            ->where('prints.0.sequence', 1)
            ->where('usage', null)
        );
});

test('the label detail page shows the full usage chain once the label has been used', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token, 'Sachet', 'Armoire', 180);
    $labelId = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    useDemoLabel($token, $tenant, $owner, $labelId);

    $this->actingAs($owner)
        ->get("/labels/{$labelId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.procedure', 'Scaling and root planing')
            ->where('practitioner.label', $owner->name)
            ->has('patient.label')
        );
});

test('a practice cannot view another practice label detail (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    [$cycleIdB] = releasedCompliantCycle($tokenB, $tenantB, $deviceIdB);
    dluRuleFor($tokenB);
    $labelIdB = $this->withToken($tokenB)->postJson("/api/v1/cycles/{$cycleIdB}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    $this->actingAs($ownerA)->get("/labels/{$labelIdB}")->assertNotFound();
});
