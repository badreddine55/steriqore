<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create a patient and is redirected to its detail page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $response = $this->actingAs($owner)->post('/patients');
    $response->assertRedirect();

    $this->actingAs($owner)
        ->get('/patients')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('traceability/patients/index')
            ->has('patients.data', 1)
            ->where('patients.data.0.reference', 'PAT-000001')
        );
});

test('an owner sees a linked procedure in the patient detail procedure history', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = scannedLabelFor($token, $tenant, $deviceId);
    // Every token/API call happens before the first actingAs() below —
    // mixing Sanctum bearer auth and web session auth out of that order
    // in the same test breaks guard resolution (established convention
    // throughout this suite, e.g. LabelsTest.php).
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId,
        'practitioner_id' => $owner->id,
        'procedure' => 'Détartrage',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($owner)
        ->get("/patients/{$patientId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('traceability/patients/show')
            ->where('patient.id', $patientId)
            ->has('usages', 1)
            ->where('usages.0.procedure', 'Détartrage')
            ->where('usages.0.label_id', $labelId)
            ->where('usages.0.practitioner_name', 'Alice Dupont')
        );
});

test('a patient with no procedures yet shows the empty state', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $patientId = Str::afterLast($this->actingAs($owner)->post('/patients')->headers->get('Location'), '/');

    $this->actingAs($owner)
        ->get("/patients/{$patientId}")
        ->assertInertia(fn (Assert $page) => $page->has('usages', 0));
});

test('patients can be filtered by reference', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    // CreatePatientAction generates PAT-000001, PAT-000002, ... sequentially
    // per tenant — deterministic, so no need to read back the first one.
    $this->actingAs($owner)->post('/patients');
    $this->actingAs($owner)->post('/patients');

    $this->actingAs($owner)
        ->get('/patients?filter[reference]=PAT-000001')
        ->assertInertia(fn (Assert $page) => $page
            ->has('patients.data', 1)
            ->where('patients.data.0.reference', 'PAT-000001')
        );
});

test('an owner can archive a patient, which then no longer appears in the list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $patientId = Str::afterLast($this->actingAs($owner)->post('/patients')->headers->get('Location'), '/');

    $this->actingAs($owner)
        ->delete("/patients/{$patientId}")
        ->assertRedirect('/patients');

    $this->actingAs($owner)
        ->get('/patients')
        ->assertInertia(fn (Assert $page) => $page->has('patients.data', 0));
});

test('a user without patients.manage cannot create or archive a patient (server-side, not just a hidden button)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $patientId = Str::afterLast($this->actingAs($owner)->post('/patients')->headers->get('Location'), '/');

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });
    $viewer = $owner->fresh();

    $this->actingAs($viewer)->post('/patients')->assertForbidden();
    $this->actingAs($viewer)->delete("/patients/{$patientId}")->assertForbidden();

    $this->actingAs($viewer)
        ->get("/patients/{$patientId}")
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));
});

test('a practice cannot see or archive another practice patient (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $foreignPatientId = Str::afterLast($this->actingAs($ownerB)->post('/patients')->headers->get('Location'), '/');

    $this->actingAs($ownerA)->get("/patients/{$foreignPatientId}")->assertNotFound();
    $this->actingAs($ownerA)->delete("/patients/{$foreignPatientId}")->assertNotFound();

    $this->actingAs($ownerA)
        ->get('/patients')
        ->assertInertia(fn (Assert $page) => $page->has('patients.data', 0));
});
