<?php

use App\Domain\Compliance\Models\NonConformity;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Inventory\Enums\BatchStatus;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Reuses fullEvidenceChainFor (tests/Feature/Api/V1/EvidenceSearchTest.php)
 * and mirrors tests/Feature/Api/V1/NonConformityTest.php's fixtures — same
 * RaiseNonConformityAction/ResolveNonConformityAction the mobile API uses.
 */
test('the cycle detail page shows the blast radius before confirming a recall', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);
    $cycleId = TenantContext::run($tenant, fn () => Label::findOrFail($chain['label_id'])->cycleItem->cycle_id);

    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nonConformityBlastRadius.labels_to_recall', 1)
            ->where('nonConformityBlastRadius.batches_to_quarantine', 1)
        );
});

test('raising a non-conformity from the web recalls every label the cycle produced and quarantines the batch', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);
    $cycleId = TenantContext::run($tenant, fn () => Label::findOrFail($chain['label_id'])->cycleItem->cycle_id);

    $this->actingAs($owner)
        ->post('/non-conformities', [
            'subject_type' => 'cycle',
            'subject_id' => $cycleId,
            'description' => 'Discovered the biological indicator reader was mis-calibrated.',
        ])
        ->assertRedirect();

    $label = Label::withoutGlobalScope(TenantScope::class)->findOrFail($chain['label_id']);
    expect($label->status)->toBe(LabelStatus::Recalled);

    $batch = Batch::withoutGlobalScope(TenantScope::class)->findOrFail($chain['batch_id']);
    expect($batch->status)->toBe(BatchStatus::Quarantined);

    $this->actingAs($owner)
        ->get('/non-conformities')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compliance/non-conformities/index')
            ->has('nonConformities.data', 1)
            ->where('nonConformities.data.0.subject_type', 'Cycle')
        );

    // The blast radius is now zero — the labels are already recalled.
    $this->actingAs($owner)
        ->get("/cycles/{$cycleId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('nonConformityBlastRadius.labels_to_recall', 0)
        );
});

test('a non-conformity can be resolved once, with a resolution note', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);
    $cycleId = TenantContext::run($tenant, fn () => Label::findOrFail($chain['label_id'])->cycleItem->cycle_id);

    $this->actingAs($owner)->post('/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleId, 'description' => 'X',
    ]);
    $ncId = TenantContext::run($tenant, fn () => NonConformity::first()->id);

    $this->actingAs($owner)
        ->post("/non-conformities/{$ncId}/resolve", ['resolution' => 'Re-tested; sensor glitch.'])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get('/non-conformities')
        ->assertInertia(fn (Assert $page) => $page
            ->where('nonConformities.data.0.resolution', 'Re-tested; sensor glitch.')
        );

    $again = $this->actingAs($owner)->post("/non-conformities/{$ncId}/resolve", ['resolution' => 'Y']);
    $again->assertRedirect();
    $again->assertSessionHas('_flash');
});

test('a stock manager (operational role) cannot raise a non-conformity from the web', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::StockManager->value);
    });

    $this->actingAs($owner->fresh())
        ->post('/non-conformities', [
            'subject_type' => 'label', 'subject_id' => $chain['label_id'], 'description' => 'X',
        ])
        ->assertForbidden();
});

test('a viewer can read non-conformities but cannot raise or resolve one (vertical escalation)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);
    $this->actingAs($owner)->post('/non-conformities', [
        'subject_type' => 'label', 'subject_id' => $chain['label_id'], 'description' => 'X',
    ]);
    $ncId = TenantContext::run($tenant, fn () => NonConformity::first()->id);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });
    $viewer = $owner->fresh();

    $this->actingAs($viewer)
        ->get('/non-conformities')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($viewer)->post('/non-conformities', [
        'subject_type' => 'label', 'subject_id' => $chain['label_id'], 'description' => 'Y',
    ])->assertForbidden();

    $this->actingAs($viewer)->post("/non-conformities/{$ncId}/resolve", ['resolution' => 'Z'])->assertForbidden();
});

test('a practice never reaches another practice cycle or label when raising a non-conformity from the web (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $chainB = fullEvidenceChainFor($tokenB, $tenantB, $ownerB->id);

    $this->actingAs($ownerA)
        ->post('/non-conformities', [
            'subject_type' => 'label', 'subject_id' => $chainB['label_id'], 'description' => 'X',
        ])
        ->assertSessionHasErrors('subject_id');
});

test('a practice never sees another practice non-conformities in the list (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $chainB = fullEvidenceChainFor($tokenB, $tenantB, $ownerB->id);
    $this->actingAs($ownerB)->post('/non-conformities', [
        'subject_type' => 'label', 'subject_id' => $chainB['label_id'], 'description' => 'X',
    ]);

    $this->actingAs($ownerA)
        ->get('/non-conformities')
        ->assertInertia(fn (Assert $page) => $page->has('nonConformities.data', 0));
});
