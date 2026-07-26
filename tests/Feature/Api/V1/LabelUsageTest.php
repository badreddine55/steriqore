<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Traceability\Models\LabelUsage;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function scannedLabelFor(string $token, Tenant $tenant, string $deviceId): string
{
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    test()->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->withToken($token)->getJson("/api/v1/labels/{$labelId}");

    return $labelId;
}

test('a practitioner can link a scanned label to a patient, procedure, and practitioner', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = scannedLabelFor($token, $tenant, $deviceId);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $usage = $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId,
        'practitioner_id' => $manager->id,
        'procedure' => 'Détartrage',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $usage->assertCreated()
        ->assertJsonPath('label_id', $labelId)
        ->assertJsonPath('patient_id', $patientId)
        ->assertJsonPath('practitioner_id', $manager->id)
        ->assertJsonPath('procedure', 'Détartrage');

    $this->withToken($token)->getJson("/api/v1/labels/{$labelId}/usage")
        ->assertOk()->assertJsonPath('procedure', 'Détartrage');
});

test('a label cannot be linked to a procedure before it has been scanned', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = generatedLabelFor($token, $tenant, $deviceId);
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $response = $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId, 'practitioner_id' => $manager->id, 'procedure' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'LABEL_NOT_SCANNED');
});

test('a label can only be linked to one usage', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = scannedLabelFor($token, $tenant, $deviceId);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId, 'practitioner_id' => $manager->id, 'procedure' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $second = $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId, 'practitioner_id' => $manager->id, 'procedure' => 'Y',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $second->assertStatus(409)->assertJsonPath('error.code', 'LABEL_USAGE_ALREADY_RECORDED');
});

test('a recalled label cannot be linked to a procedure even after being scanned (defense in depth)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = scannedLabelFor($token, $tenant, $deviceId);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId)->update(['status' => LabelStatus::Recalled]);

    $response = $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId, 'practitioner_id' => $manager->id, 'procedure' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'LABEL_NOT_USABLE');
});

test('linking to a foreign-tenant patient or practitioner is rejected by validation', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = scannedLabelFor($token, $tenant, $deviceId);

    [$foreignTenant, $foreignUser] = sterilizationTenantWithMember(TenantRole::Owner);
    $foreignToken = sterilizationTokenFor($foreignTenant, $foreignUser);
    $foreignPatientId = $this->withToken($foreignToken)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $foreignPatientId, 'practitioner_id' => $manager->id, 'procedure' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);

    $ownPatientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $ownPatientId, 'practitioner_id' => $foreignUser->id, 'procedure' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});

test('a viewer can view a usage but cannot record one (vertical escalation)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($managerToken, $tenant);
    $labelId = scannedLabelFor($managerToken, $tenant, $deviceId);
    $patientId = $this->withToken($managerToken)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    $this->withToken($managerToken)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId, 'practitioner_id' => $manager->id, 'procedure' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    // The viewer must be a member of the SAME tenant as the label.
    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = sterilizationTokenFor($tenant, $viewer);

    $this->withToken($viewerToken)->getJson("/api/v1/labels/{$labelId}/usage")->assertOk();
    $this->withToken($viewerToken)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId, 'practitioner_id' => $manager->id, 'procedure' => 'Y',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('label_usages is an evidence table: there is no delete route', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = scannedLabelFor($token, $tenant, $deviceId);
    $patientId = $this->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    $usageId = $this->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patientId, 'practitioner_id' => $manager->id, 'procedure' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($token)->deleteJson("/api/v1/labels/{$labelId}/usage")->assertStatus(405);
    expect(LabelUsage::withoutGlobalScope(TenantScope::class)->find($usageId))->not->toBeNull();
});
