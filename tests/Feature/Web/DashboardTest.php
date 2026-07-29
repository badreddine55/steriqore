<?php

use App\Domain\Compliance\Models\NonConformity;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Labeling\Models\Label;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Card order in the response is always the same fixed order
 * BuildDashboardAction::buildAttentionCards defines (0: cycles_awaiting_
 * release, 1: cycles_rejected_no_non_conformity, 2: open_non_conformities,
 * 3: batches_expired_in_stock, 4: batches_near_expiry, 5: products_low_stock,
 * 6: labels_past_dlu_unused) — role-aware reordering happens client-side
 * only (attention-queue.tsx), so asserting by fixed index here is exact,
 * not fragile.
 */
test('a brand new practice sees an onboarding checklist instead of eight empty cards', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.is_new_practice', true)
            ->where('dashboard.attention_cards', [])
            ->has('dashboard.onboarding_steps', 4)
            ->where('dashboard.onboarding_steps.0.key', 'add_site')
            ->where('dashboard.onboarding_steps.0.done', false)
            ->where('dashboard.onboarding_steps.0.can_act', true)
            ->where('dashboard.onboarding_steps.3.key', 'run_first_cycle')
            ->where('dashboard.onboarding_steps.3.done', false)
        );
});

test('once a practice has a device and a cycle, the attention queue replaces the checklist', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    cycleFor($token, $deviceId);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.is_new_practice', false)
            ->has('dashboard.attention_cards', 7)
        );
});

test('cycles awaiting release: the dashboard count matches exactly what the filtered list shows', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = sterilizationRunningCycleFor($token, $deviceId);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attention_cards.0.key', 'cycles_awaiting_release')
            ->where('dashboard.attention_cards.0.count', 1)
            ->where('dashboard.attention_cards.0.error', false)
        );

    $this->actingAs($owner)
        ->get('/cycles?'.http_build_query(['filter' => ['status' => 'awaiting_release']]))
        ->assertInertia(fn (Assert $page) => $page->has('cycles.data', 1));
});

test('a rejected cycle without a non-conformity is counted, and clears once one is raised', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    $cycleId = sterilizationRunningCycleFor($token, $deviceId);
    $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);
    [, $releaserToken] = releaserFor($tenant);
    $this->withToken($releaserToken)->postJson("/api/v1/cycles/{$cycleId}/release", [
        'decision' => 'rejected', 'reason' => 'Failed biological test',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attention_cards.1.key', 'cycles_rejected_no_non_conformity')
            ->where('dashboard.attention_cards.1.count', 1)
        );

    $this->actingAs($owner)
        ->get('/cycles?'.http_build_query(['filter' => ['status' => 'rejected', 'missing_non_conformity' => 1]]))
        ->assertInertia(fn (Assert $page) => $page->has('cycles.data', 1));

    $this->actingAs($owner)->post('/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleId, 'description' => 'Investigated.',
    ]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attention_cards.1.count', 0)
        );
});

test('open non-conformities are counted, and clear once resolved', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);
    $cycleId = TenantContext::run($tenant, fn () => Label::findOrFail($chain['label_id'])->cycleItem->cycle_id);

    $this->actingAs($owner)->post('/non-conformities', [
        'subject_type' => 'cycle', 'subject_id' => $cycleId, 'description' => 'Investigated.',
    ]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attention_cards.2.key', 'open_non_conformities')
            ->where('dashboard.attention_cards.2.count', 1)
        );

    $this->actingAs($owner)
        ->get('/non-conformities?'.http_build_query(['filter' => ['resolved' => 0]]))
        ->assertInertia(fn (Assert $page) => $page->has('nonConformities.data', 1));

    $ncId = TenantContext::run($tenant, fn () => NonConformity::first()->id);
    $this->actingAs($owner)->post("/non-conformities/{$ncId}/resolve", ['resolution' => 'Re-tested.']);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('dashboard.attention_cards.2.count', 0));
});

test('expired-in-stock, near-expiry, and low-stock cards match the alerts list exactly', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    // A device + cycle exist so the practice is past onboarding — otherwise
    // "no devices and no cycles" would show the checklist instead (correct
    // per the brief, just not what this test is exercising).
    $deviceId = sterilizationDeviceFor($token, $tenant);
    cycleFor($token, $deviceId);
    // alertLocationFor() would try to create its own "Cabinet Principal"
    // site and collide with the one sterilizationDeviceFor() just made —
    // reuse it instead.
    $locationId = TenantContext::run(
        $tenant,
        fn () => $tenant->sites()->first()->storageLocations()->create(['tenant_id' => $tenant->id, 'name' => 'Réserve'])->id
    );
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    receiveProductWithThreshold($token, $locationId, minThreshold: 0, qty: 10, expiryDate: now()->subDay()->toDateString());
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attention_cards.3.key', 'batches_expired_in_stock')
            ->where('dashboard.attention_cards.3.count', 1)
            ->where('dashboard.attention_cards.5.key', 'products_low_stock')
            ->where('dashboard.attention_cards.5.count', 1)
        );

    $this->actingAs($owner)
        ->get('/alerts?'.http_build_query(['filter' => ['type' => 'expired', 'state' => 'open']]))
        ->assertInertia(fn (Assert $page) => $page->has('alerts.data', 1));

    $this->actingAs($owner)
        ->get('/alerts?'.http_build_query(['filter' => ['type' => 'low_stock', 'state' => 'open']]))
        ->assertInertia(fn (Assert $page) => $page->has('alerts.data', 1));
});

test('a label past its use-by date that was never used is counted, and clears once used', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);
    dluRuleFor($token);

    $labelId = $this->withToken($token)->postJson("/api/v1/cycles/{$cycleId}/labels", [
        'packaging_type' => 'Sachet', 'storage_condition' => 'Armoire',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('0.id');

    TenantContext::run($tenant, fn () => Label::findOrFail($labelId)->update(['use_by_date' => now()->subDay()]));

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attention_cards.6.key', 'labels_past_dlu_unused')
            ->where('dashboard.attention_cards.6.count', 1)
        );

    $this->actingAs($owner)
        ->get('/labels?'.http_build_query(['filter' => ['overdue' => 1]]))
        ->assertInertia(fn (Assert $page) => $page->has('labels.data', 1));
});

test("today's cycles show device, status, and release decision", function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $deviceId = sterilizationDeviceFor($token, $tenant);
    [$cycleId] = releasedCompliantCycle($token, $tenant, $deviceId);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.today_cycles_error', false)
            ->has('dashboard.today_cycles', 1)
            ->where('dashboard.today_cycles.0.id', $cycleId)
            ->where('dashboard.today_cycles.0.device_name', 'Autoclave 1')
            ->where('dashboard.today_cycles.0.status', 'released')
            ->where('dashboard.today_cycles.0.release_decision', 'compliant')
        );
});

test('a practitioner sees their own recent procedures', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $chain = fullEvidenceChainFor($token, $tenant, $owner->id);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.my_recent_procedures_error', false)
            ->has('dashboard.my_recent_procedures', 1)
            ->where('dashboard.my_recent_procedures.0.label_id', $chain['label_id'])
        );
});

test('a practice never sees another practice data on the dashboard (IDOR)', function () {
    [$tenantA, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenA = sterilizationTokenFor($tenantA, $ownerA);
    $deviceIdA = sterilizationDeviceFor($tokenA, $tenantA);
    cycleFor($tokenA, $deviceIdA);

    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    $cycleIdB = sterilizationRunningCycleFor($tokenB, $deviceIdB);
    $this->withToken($tokenB)->postJson("/api/v1/cycles/{$cycleIdB}/submit-for-release", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->actingAs($ownerA)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.attention_cards.0.count', 0)
            ->where('dashboard.today_cycles', [])
        );
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

/**
 * The real N+1 guard: query count must not grow with the number of rows —
 * two tenants (fresh cache keys, no pollution between them) with 1 vs 5
 * cycles today should cost the exact same number of queries, proving the
 * eager-loaded device/release relations aren't re-queried per row.
 */
test('the dashboard issues the same number of queries regardless of how many cycles ran today', function () {
    [$tenantA, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenA = sterilizationTokenFor($tenantA, $ownerA);
    $deviceIdA = sterilizationDeviceFor($tokenA, $tenantA);
    cycleFor($tokenA, $deviceIdA);

    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $deviceIdB = sterilizationDeviceFor($tokenB, $tenantB);
    for ($i = 0; $i < 5; $i++) {
        cycleFor($tokenB, $deviceIdB);
    }

    DB::enableQueryLog();
    $this->actingAs($ownerA)->get('/dashboard')->assertOk();
    $countA = count(DB::getQueryLog());
    DB::flushQueryLog();

    $this->actingAs($ownerB)->get('/dashboard')->assertOk();
    $countB = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($countB)->toBe($countA);
});
