<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Labeling\Models\Label;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Read-only (UI-24) — the only writer is RaiseNonConformityAction
 * (UI-23), so every fixture here goes through the real recall flow
 * rather than setting Batch::status directly.
 */
test('a batch quarantined by a recall appears with who, why, and when', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);
    $cycleId = TenantContext::run($tenant, fn () => Label::findOrFail($chain['label_id'])->cycleItem->cycle_id);

    $this->actingAs($owner)->post('/non-conformities', [
        'subject_type' => 'cycle',
        'subject_id' => $cycleId,
        'description' => 'Discovered the biological indicator reader was mis-calibrated.',
    ]);

    $this->actingAs($owner)
        ->get('/quarantine')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/quarantine/index')
            ->has('batches.data', 1)
            ->where('batches.data.0.reason', 'Discovered the biological indicator reader was mis-calibrated.')
            ->where('batches.data.0.quarantined_by', fn ($value) => str_starts_with($value, 'Alice Dupont'))
            ->where('batches.data.0.qty_on_hand', 10)
        );
});

test('an active (non-quarantined) batch does not appear in the quarantine list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    fullEvidenceChainFor($token, $tenant, $owner->id);

    $this->actingAs($owner)
        ->get('/quarantine')
        ->assertInertia(fn (Assert $page) => $page->has('batches.data', 0));
});

test('a viewer can see the quarantine list (inventory.view is enough)', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())->get('/quarantine')->assertOk();
});

test('a user without inventory.view is refused the quarantine list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
    });

    $this->actingAs($owner->fresh())->get('/quarantine')->assertForbidden();
});

test('a practice never sees another practice quarantined batches (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $chainB = fullEvidenceChainFor($tokenB, $tenantB, $ownerB->id);
    $cycleIdB = TenantContext::run($tenantB, fn () => Label::findOrFail($chainB['label_id'])->cycleItem->cycle_id);
    $this->actingAs($ownerB)->post('/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleIdB, 'description' => 'X',
    ]);

    $this->actingAs($ownerA)
        ->get('/quarantine')
        ->assertInertia(fn (Assert $page) => $page->has('batches.data', 0));
});
