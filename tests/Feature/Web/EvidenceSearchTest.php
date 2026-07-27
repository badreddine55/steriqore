<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Inventory\Models\Batch;
use App\Support\Tenancy\TenantScope;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Reuses fullEvidenceChainFor (tests/Feature/Api/V1/EvidenceSearchTest.php)
 * — the real receive -> cycle -> release -> label -> scan -> usage chain,
 * same SearchEvidenceAction/EvidenceSearchResultData the mobile API uses.
 */
test('an owner finds evidence by patient, cycle, batch, device, operator, and period', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);

    $this->actingAs($owner)
        ->get('/evidence-search?patient_reference='.$chain['patient_reference'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('traceability/evidence-search/index')
            ->has('results.data', 1)
            ->where('results.data.0.label_id', $chain['label_id'])
        );

    $this->actingAs($owner)
        ->get('/evidence-search?cycle_number='.$chain['cycle_number'])
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1));

    $this->actingAs($owner)
        ->get('/evidence-search?device_id='.$chain['device_id'])
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1));

    $this->actingAs($owner)
        ->get('/evidence-search?operator_id='.$owner->id)
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1));

    $this->actingAs($owner)
        ->get('/evidence-search?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString())
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1));

    $this->actingAs($owner)
        ->get('/evidence-search?from='.now()->addYear()->toDateString())
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
});

test('an owner finds evidence by batch number', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);
    $batchNumber = Batch::withoutGlobalScope(TenantScope::class)->findOrFail($chain['batch_id'])->batch_number;

    $this->actingAs($owner)
        ->get('/evidence-search?batch_number='.$batchNumber)
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1));
});

test('a search with no matching filters returns an empty result, not an error', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->get('/evidence-search?patient_reference=PAT-999999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
});

test('a viewer can search evidence (view-only permission is enough)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    fullEvidenceChainFor($token, $tenant, $owner->id);

    [$viewerTenant, $viewer] = sterilizationTenantWithMember(TenantRole::Viewer);

    $this->actingAs($viewer)->get('/evidence-search')->assertOk();
});

test('archiving the patient afterwards does not break the evidence search result (invariant #6: past evidence keeps its identity)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);

    $this->withToken($token)->deleteJson("/api/v1/patients/{$chain['patient_id']}");

    $this->actingAs($owner)
        ->get('/evidence-search?cycle_number='.$chain['cycle_number'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('results.data', 1)
            ->where('results.data.0.patient_reference', $chain['patient_reference'])
        );
});

test('a practice never finds another practice evidence, even without any filter (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    fullEvidenceChainFor($tokenB, $tenantB, $ownerB->id);

    $this->actingAs($ownerA)
        ->get('/evidence-search')
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 0));
});
