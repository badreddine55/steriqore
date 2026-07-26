<?php

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Inventory\Enums\BatchStatus;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Tenancy\Models\Site;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

test('raising a non-conformity against a cycle recalls every label it produced and quarantines the batch', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($token, $tenant, $manager->id);

    $cycleId = Label::withoutGlobalScope(TenantScope::class)->findOrFail($chain['label_id'])->cycleItem()->withoutGlobalScope(TenantScope::class)->first()->cycle_id;

    $nc = $this->withToken($token)->postJson('/api/v1/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleId, 'description' => 'Discovered the biological indicator reader was mis-calibrated.',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $nc->assertCreated()->assertJsonPath('subject_type', 'Cycle');

    $label = Label::withoutGlobalScope(TenantScope::class)->findOrFail($chain['label_id']);
    expect($label->status)->toBe(LabelStatus::Recalled);

    $batch = Batch::withoutGlobalScope(TenantScope::class)->findOrFail($chain['batch_id']);
    expect($batch->status)->toBe(BatchStatus::Quarantined);

    expect(
        AuditEvent::withoutGlobalScope(TenantScope::class)->where('action', 'label.recalled')->where('subject_id', $chain['label_id'])->exists()
    )->toBeTrue();
    expect(
        AuditEvent::withoutGlobalScope(TenantScope::class)->where('action', 'batch.quarantined')->where('subject_id', $chain['batch_id'])->exists()
    )->toBeTrue();
});

test('a scan of a recalled label now returns 410', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $labelId = scannedLabelFor($token, $tenant, $deviceId);
    $cycleId = Label::withoutGlobalScope(TenantScope::class)->findOrFail($labelId)->cycleItem()->withoutGlobalScope(TenantScope::class)->first()->cycle_id;

    $this->withToken($token)->postJson('/api/v1/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleId, 'description' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->withToken($token)->getJson("/api/v1/labels/{$labelId}")
        ->assertStatus(410)->assertJsonPath('error.code', 'LABEL_RECALLED');
});

test('a quarantined batch can no longer be issued', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($token, $tenant, $manager->id);
    $cycleId = Label::withoutGlobalScope(TenantScope::class)->findOrFail($chain['label_id'])->cycleItem()->withoutGlobalScope(TenantScope::class)->first()->cycle_id;
    $this->withToken($token)->postJson('/api/v1/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleId, 'description' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $locationId = Site::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->firstOrFail()
        ->storageLocations()->create(['tenant_id' => $tenant->id, 'name' => 'Autre réserve'])->id;

    $response = $this->withToken($token)->postJson('/api/v1/stock-movements/issue', [
        'batch_id' => $chain['batch_id'], 'location_id' => $locationId, 'qty' => 1,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'BATCH_QUARANTINED');
});

test('a non-conformity can be resolved once, with a resolution note', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($token, $tenant, $manager->id);
    $cycleId = Label::withoutGlobalScope(TenantScope::class)->findOrFail($chain['label_id'])->cycleItem()->withoutGlobalScope(TenantScope::class)->first()->cycle_id;
    $ncId = $this->withToken($token)->postJson('/api/v1/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleId, 'description' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $resolve = $this->withToken($token)->postJson("/api/v1/non-conformities/{$ncId}/resolve", [
        'resolution' => 'Re-tested the device; issue was a one-off sensor glitch.',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $resolve->assertCreated()->assertJsonPath('resolution', 'Re-tested the device; issue was a one-off sensor glitch.');

    $again = $this->withToken($token)->postJson("/api/v1/non-conformities/{$ncId}/resolve", [
        'resolution' => 'Y',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $again->assertStatus(409)->assertJsonPath('error.code', 'NON_CONFORMITY_ALREADY_RESOLVED');
});

test('a viewer can read non-conformities but cannot raise one (vertical escalation)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $managerToken = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($managerToken, $tenant, $manager->id);

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = sterilizationTokenFor($tenant, $viewer);

    $this->withToken($viewerToken)->getJson('/api/v1/non-conformities')->assertOk();
    $this->withToken($viewerToken)->postJson('/api/v1/non-conformities', [
        'subject_type' => 'label', 'subject_id' => $chain['label_id'], 'description' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a stock manager (operational role) cannot raise a non-conformity — that stays with releaser/owner/admin', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($token, $tenant, $manager->id);

    $stockUser = User::create(['name' => 'SM', 'email' => 'sm-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $stockUser->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $stockUser->assignRole(TenantRole::StockManager->value);
    $registrar->setPermissionsTeamId(null);
    $stockToken = sterilizationTokenFor($tenant, $stockUser);

    $this->withToken($stockToken)->postJson('/api/v1/non-conformities', [
        'subject_type' => 'label', 'subject_id' => $chain['label_id'], 'description' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice cycle or label when raising a non-conformity (IDOR)', function () {
    [$tenantA, $managerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $managerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenA = sterilizationTokenFor($tenantA, $managerA);
    $tokenB = sterilizationTokenFor($tenantB, $managerB);
    $chainB = fullEvidenceChainFor($tokenB, $tenantB, $managerB->id);

    $this->withToken($tokenA)->postJson('/api/v1/non-conformities', [
        'subject_type' => 'label', 'subject_id' => $chainB['label_id'], 'description' => 'X',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
});
