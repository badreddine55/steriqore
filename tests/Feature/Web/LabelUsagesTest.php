<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Traceability\Models\LabelUsage;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The web fallback for the mobile scan (UI-20) — reuses
 * ResolveLabelScanAction + RecordLabelUsageAction exactly as a real scan
 * would, so these tests deliberately start from a PRINTED (not yet
 * scanned) label, proving the web endpoint performs the scan transition
 * itself rather than assuming it already happened.
 */
test('linking a printed, not-yet-scanned label to a procedure via the web transitions it to used', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->actingAs($owner)
        ->post("/labels/{$labelId}/usage", [
            'patient_id' => $patientId,
            'practitioner_id' => $owner->id,
            'procedure' => 'Détartrage',
        ])
        ->assertRedirect();

    $label = Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId);
    expect($label->status)->toBe(LabelStatus::Used);

    $this->actingAs($owner)
        ->get("/labels/{$labelId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.procedure', 'Détartrage')
            ->where('usage.patient_id', $patientId)
            ->where('label.status', 'used')
        );
});

test('a recalled label cannot be linked to a procedure via the web fallback either', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId)->update(['status' => LabelStatus::Recalled]);

    $response = $this->actingAs($owner)->post("/labels/{$labelId}/usage", [
        'patient_id' => $patientId,
        'practitioner_id' => $owner->id,
        'procedure' => 'X',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('_flash');
    expect(LabelUsage::withoutGlobalScope(TenantScope::class)->where('label_id', $labelId)->exists())->toBeFalse();
});

test('a viewer cannot record a label usage (server-side, not just a hidden form)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });
    $viewer = $owner->fresh();

    $this->actingAs($viewer)
        ->post("/labels/{$labelId}/usage", [
            'patient_id' => $patientId,
            'practitioner_id' => $owner->id,
            'procedure' => 'X',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get("/labels/{$labelId}")
        ->assertInertia(fn (Assert $page) => $page->where('canRecordUsage', false));
});

test('a practice cannot link another practice label to a procedure (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    $labelIdB = generatedLabelFor($tokenB, $tenantB, $deviceIdB);
    $this->withToken($tokenB)->postJson("/api/v1/labels/{$labelIdB}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($ownerA)
        ->post("/labels/{$labelIdB}/usage", [
            'patient_id' => (string) Str::uuid(),
            'practitioner_id' => $ownerA->id,
            'procedure' => 'X',
        ])
        ->assertNotFound();
});
