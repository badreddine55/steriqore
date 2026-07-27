<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Reuses invLocationFor/receiveStockFor (tests/Feature/Api/V1/InventoryTest.php)
 * — the real purchasing flow that actually populates stock_movements/
 * stock_levels, rather than writing rows directly. Both screens here are
 * read-only (CLAUDE.md invariant #1: stock is derived, never set), so
 * every fixture in this file goes through that real flow or the mobile
 * API's issue/adjust/transfer endpoints — never a direct Eloquent write.
 */
test('an owner sees stock on hand for a received batch, by product, batch and location', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = invLocationFor($tenant);
    ['batch_id' => $batchId, 'product_id' => $productId] = receiveStockFor($token, $locationId, 20);

    $this->actingAs($owner)
        ->get('/stock')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/stock/index')
            ->has('levels.data', 1)
            ->where('levels.data.0.batch_id', $batchId)
            ->where('levels.data.0.product_id', $productId)
            ->where('levels.data.0.location_id', $locationId)
            ->where('levels.data.0.quantity', 20)
        );
});

test('an owner sees the receipt movement in the ledger with a positive signed quantity', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = invLocationFor($tenant);
    receiveStockFor($token, $locationId, 20);

    $this->actingAs($owner)
        ->get('/stock/movements')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/stock/movements')
            ->has('movements.data', 1)
            ->where('movements.data.0.type', 'receipt')
            ->where('movements.data.0.qty', 20)
        );
});

test('issuing stock via the API is reflected on the web as a lower level and a negative ledger entry', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $token = sterilizationTokenFor($tenant, $owner);
    $locationId = invLocationFor($tenant);
    ['batch_id' => $batchId] = receiveStockFor($token, $locationId, 20);

    $this->withToken($token)->postJson('/api/v1/stock-movements/issue', [
        'batch_id' => $batchId,
        'location_id' => $locationId,
        'qty' => 5,
        'reason' => 'Used for cycle #1.',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($owner)
        ->get('/stock')
        ->assertInertia(fn (Assert $page) => $page
            ->where('levels.data.0.quantity', 15)
        );

    $this->actingAs($owner)
        ->get('/stock/movements?filter[type]=issue')
        ->assertInertia(fn (Assert $page) => $page
            ->has('movements.data', 1)
            ->where('movements.data.0.type', 'issue')
            ->where('movements.data.0.qty', -5)
            ->where('movements.data.0.reason', 'Used for cycle #1.')
        );
});

test('a practice cannot see another practice stock levels or movements (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $tokenB = sterilizationTokenFor($tenantB, $ownerB);
    $locationIdB = invLocationFor($tenantB);
    receiveStockFor($tokenB, $locationIdB, 20);

    $this->actingAs($ownerA)
        ->get('/stock')
        ->assertInertia(fn (Assert $page) => $page->has('levels.data', 0));

    $this->actingAs($ownerA)
        ->get('/stock/movements')
        ->assertInertia(fn (Assert $page) => $page->has('movements.data', 0));
});

test('a user without inventory.view is refused both stock screens', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
    });

    $this->actingAs($owner->fresh())->get('/stock')->assertForbidden();
    $this->actingAs($owner->fresh())->get('/stock/movements')->assertForbidden();
});
