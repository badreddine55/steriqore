<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * StorageLocation has no Action/API/Web route anywhere yet (flagged and
 * confirmed with the product owner while building this screen) — a real
 * practice can't create one through the app until that lands (S-ANA "Sites,
 * rooms, storage locations"). Created directly here, the same way Sites
 * already are in sterilizationDeviceFor() elsewhere in this test suite,
 * purely as fixture setup for a record type nothing in the domain writes
 * yet.
 */
function storageLocationFor(Tenant $tenant): string
{
    return TenantContext::run($tenant, function () use ($tenant) {
        $siteId = $tenant->sites()->create(['name' => 'Cabinet Principal'])->id;

        return StorageLocation::create([
            'tenant_id' => $tenant->id,
            'site_id' => $siteId,
            'name' => 'Main storeroom',
        ])->id;
    });
}

test('goods can be received in full, updating the purchase order to received', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    $locationId = storageLocationFor($tenant);

    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ]);
    $orderId = Str::afterLast($create->headers->get('Location'), '/');
    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/order")->assertRedirect();

    $lineId = TenantContext::run($tenant, fn () => PurchaseOrder::find($orderId)->lines()->first()->id);

    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/receipts", [
        'location_id' => $locationId,
        'lines' => [[
            'purchase_order_line_id' => $lineId,
            'batch_number' => 'BATCH-001',
            'expiry_date' => now()->addYear()->toDateString(),
            'qty' => 10,
        ]],
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/purchase-orders/{$orderId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('purchaseOrder.status', 'received')
            ->where('purchaseOrder.lines.0.qty_received', 10)
            ->has('goodsReceipts', 1)
            ->where('goodsReceipts.0.lines.0.qty', 10)
        );

    expect(Batch::withoutGlobalScope(TenantScope::class)->where('batch_number', 'BATCH-001')->exists())->toBeTrue();
});

test('goods can be partially received, with a discrepancy reason, leaving the order partially received', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    $locationId = storageLocationFor($tenant);

    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ]);
    $orderId = Str::afterLast($create->headers->get('Location'), '/');
    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/order");
    $lineId = TenantContext::run($tenant, fn () => PurchaseOrder::find($orderId)->lines()->first()->id);

    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/receipts", [
        'location_id' => $locationId,
        'lines' => [[
            'purchase_order_line_id' => $lineId,
            'batch_number' => 'BATCH-002',
            'qty' => 6,
            'discrepancy_reason' => '4 units damaged in transit.',
        ]],
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/purchase-orders/{$orderId}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('purchaseOrder.status', 'partially_received')
            ->where('purchaseOrder.lines.0.qty_received', 6)
            ->where('goodsReceipts.0.lines.0.discrepancy_reason', '4 units damaged in transit.')
        );
});

test('receiving against a nonexistent storage location is a validation error, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);

    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ]);
    $orderId = Str::afterLast($create->headers->get('Location'), '/');
    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/order");
    $lineId = TenantContext::run($tenant, fn () => PurchaseOrder::find($orderId)->lines()->first()->id);

    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/receipts", [
        'location_id' => (string) Str::uuid(),
        'lines' => [[
            'purchase_order_line_id' => $lineId,
            'batch_number' => 'BATCH-003',
            'qty' => 10,
        ]],
    ])->assertSessionHasErrors('location_id');
});

test('a viewer cannot receive goods', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    $locationId = storageLocationFor($tenant);
    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ]);
    $orderId = Str::afterLast($create->headers->get('Location'), '/');
    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/order");
    $lineId = TenantContext::run($tenant, fn () => PurchaseOrder::find($orderId)->lines()->first()->id);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });

    $this->actingAs($owner->fresh())->post("/purchase-orders/{$orderId}/receipts", [
        'location_id' => $locationId,
        'lines' => [['purchase_order_line_id' => $lineId, 'batch_number' => 'X', 'qty' => 1]],
    ])->assertForbidden();
});

test('a practice cannot receive goods against another practice purchase order (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierIdB, $productIdB] = supplierAndProductFor($ownerB);
    $locationIdB = storageLocationFor($tenantB);
    $create = $this->actingAs($ownerB)->post('/purchase-orders', [
        'supplier_id' => $supplierIdB,
        'lines' => [['product_id' => $productIdB, 'qty_ordered' => 10]],
    ]);
    $orderIdB = Str::afterLast($create->headers->get('Location'), '/');
    $this->actingAs($ownerB)->post("/purchase-orders/{$orderIdB}/order");
    $lineIdB = TenantContext::run($tenantB, fn () => PurchaseOrder::find($orderIdB)->lines()->first()->id);

    $this->actingAs($ownerA)->post("/purchase-orders/{$orderIdB}/receipts", [
        'location_id' => $locationIdB,
        'lines' => [['purchase_order_line_id' => $lineIdB, 'batch_number' => 'X', 'qty' => 1]],
    ])->assertNotFound();
});
