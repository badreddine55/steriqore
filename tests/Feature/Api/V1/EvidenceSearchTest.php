<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Str;

/**
 * @return array{label_id: string, cycle_number: int, device_id: string, batch_id: string, patient_id: string, patient_reference: string}
 */
function fullEvidenceChainFor(string $token, Tenant $tenant, string $practitionerId): array
{
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $site = Site::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id)->firstOrFail();
    $locationId = $site->storageLocations()->create(['tenant_id' => $tenant->id, 'name' => 'Réserve'])->id;
    $received = receiveProductWithThreshold($token, $locationId, minThreshold: 0, qty: 10);

    $cycleId = test()->withToken($token)->postJson('/api/v1/cycles', ['device_id' => $deviceId], ['Idempotency-Key' => (string) Str::uuid()])->json('id');
    $cycleNumber = test()->withToken($token)->getJson("/api/v1/cycles/{$cycleId}")->json('cycle_number');

    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/items", [
        'description' => 'Kit chirurgical', 'batch_id' => $received['batch_id'],
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/start", [], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/complete", [], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);
    [, $releaserToken] = releaserFor($tenant);
    test()->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", ['decision' => 'compliant'], ['Idempotency-Key' => (string) Str::uuid()]);

    dluRuleFor($token);
    $labelId = test()->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');
    test()->withToken($token)->postJson("/api/v1/labels/{$labelId}/print", [], ['Idempotency-Key' => (string) Str::uuid()]);
    test()->withToken($token)->getJson("/api/v1/labels/{$labelId}");

    $patient = test()->withToken($token)->postJson('/api/v1/patients', [], ['Idempotency-Key' => (string) Str::uuid()]);

    test()->withToken($token)->postJson("/api/v1/labels/{$labelId}/usage", [
        'patient_id' => $patient->json('id'),
        'practitioner_id' => $practitionerId,
        'procedure' => 'Détartrage',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    return [
        'label_id' => $labelId,
        'cycle_number' => $cycleNumber,
        'device_id' => $deviceId,
        'batch_id' => $received['batch_id'],
        'patient_id' => $patient->json('id'),
        'patient_reference' => $patient->json('reference'),
    ];
}

test('evidence search finds a usage by patient reference, cycle number, device, and period', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($token, $tenant, $manager->id);

    $byPatient = $this->withToken($token)->getJson('/api/v1/evidence-search?patient_reference='.$chain['patient_reference']);
    $byPatient->assertOk();
    expect($byPatient->json('data'))->toHaveCount(1);
    expect($byPatient->json('data.0.label_id'))->toBe($chain['label_id']);

    $byCycle = $this->withToken($token)->getJson('/api/v1/evidence-search?cycle_number='.$chain['cycle_number']);
    expect($byCycle->json('data'))->toHaveCount(1);

    $byDevice = $this->withToken($token)->getJson('/api/v1/evidence-search?device_id='.$chain['device_id']);
    expect($byDevice->json('data'))->toHaveCount(1);

    $byPeriod = $this->withToken($token)->getJson('/api/v1/evidence-search?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString());
    expect($byPeriod->json('data'))->toHaveCount(1);

    $outsidePeriod = $this->withToken($token)->getJson('/api/v1/evidence-search?from='.now()->addYear()->toDateString());
    expect($outsidePeriod->json('data'))->toBeEmpty();
});

test('evidence search finds a usage by batch number', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    $chain = fullEvidenceChainFor($token, $tenant, $manager->id);

    $batchNumber = $this->withToken($token)->getJson('/api/v1/evidence-search?patient_reference='.$chain['patient_reference'])
        ->json('data.0.batch_number');
    expect($batchNumber)->not->toBeNull();

    $bySearch = $this->withToken($token)->getJson('/api/v1/evidence-search?batch_number='.$batchNumber);
    expect($bySearch->json('data'))->toHaveCount(1);
});

test('a search with no matching filters returns an empty result, not an error', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);

    $response = $this->withToken($token)->getJson('/api/v1/evidence-search?patient_reference=PAT-999999');
    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('a viewer can search evidence (view-only permission is enough)', function () {
    [$tenant, $manager] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $manager);
    fullEvidenceChainFor($token, $tenant, $manager->id);

    [$viewerTenant, $viewer] = sterilizationTenantWithMember(TenantRole::Viewer);
    $viewerToken = sterilizationTokenFor($viewerTenant, $viewer);

    $this->withToken($viewerToken)->getJson('/api/v1/evidence-search')->assertOk();
});

test('a practice never finds another practice evidence, even without any filter (IDOR)', function () {
    [$tenantA, $managerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $managerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenA = sterilizationTokenFor($tenantA, $managerA);
    $tokenB = sterilizationTokenFor($tenantB, $managerB);
    fullEvidenceChainFor($tokenB, $tenantB, $managerB->id);

    $response = $this->withToken($tokenA)->getJson('/api/v1/evidence-search');
    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});
