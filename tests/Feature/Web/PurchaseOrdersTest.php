<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: string, 1: string} [supplierId, productId]
 */
function supplierAndProductFor(mixed $owner): array
{
    $supplierCreate = test()->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical '.Str::random(6)]);
    $supplierId = Str::afterLast($supplierCreate->headers->get('Location'), '/');

    $productCreate = test()->actingAs($owner)->post('/products', [
        'name' => 'Sterilization pouch', 'reference' => 'SKU-'.Str::random(8),
    ]);
    $productId = Str::afterLast($productCreate->headers->get('Location'), '/');

    return [$supplierId, $productId];
}

test('an owner can view the purchase orders list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);

    $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 50]],
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get('/purchase-orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/purchase-orders/index')
            ->has('purchaseOrders.data', 1)
            ->where('purchaseOrders.data.0.supplier_id', $supplierId)
            ->where('canCreate', true)
        );
});

test('a purchase order can be created with lines, viewed, and marked as ordered', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);

    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 50, 'unit_price' => '12.50']],
    ]);
    $create->assertRedirect();
    $orderId = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($owner)
        ->get("/purchase-orders/{$orderId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/purchase-orders/show')
            ->where('purchaseOrder.status', 'draft')
            ->has('purchaseOrder.lines', 1)
            ->where('purchaseOrder.lines.0.qty_ordered', 50)
            ->where('canManage', true)
        );

    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/order")->assertRedirect();

    $this->actingAs($owner)
        ->get("/purchase-orders/{$orderId}")
        ->assertInertia(fn (Assert $page) => $page->where('purchaseOrder.status', 'ordered'));
});

test('marking an already-ordered purchase order as ordered again is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ]);
    $orderId = Str::afterLast($create->headers->get('Location'), '/');
    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/order")->assertRedirect();

    // ApiExceptionWebRenderer converts INVALID_STATE_TRANSITION into a
    // normal redirect-back, not the API's JSON envelope or a raw 500.
    $response = $this->actingAs($owner)->post("/purchase-orders/{$orderId}/order");
    $response->assertRedirect();
    $response->assertSessionHas('_flash');
});

test('a purchase order can be cancelled with an optional reason', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ]);
    $orderId = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($owner)->post("/purchase-orders/{$orderId}/cancel", [
        'reason' => 'Supplier out of stock.',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/purchase-orders/{$orderId}")
        ->assertInertia(fn (Assert $page) => $page->where('purchaseOrder.status', 'cancelled'));
});

test('creating a purchase order without lines is a validation error', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId] = supplierAndProductFor($owner);

    $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [],
    ])->assertSessionHasErrors('lines');
});

test('a viewer cannot create or manage a purchase order', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    $create = $this->actingAs($owner)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ]);
    $orderId = Str::afterLast($create->headers->get('Location'), '/');

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });
    $viewer = $owner->fresh();

    $this->actingAs($viewer)->post('/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 5]],
    ])->assertForbidden();
    $this->actingAs($viewer)->post("/purchase-orders/{$orderId}/order")->assertForbidden();
});

test('a practice cannot view another practice purchase order (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierIdB, $productIdB] = supplierAndProductFor($ownerB);
    $create = $this->actingAs($ownerB)->post('/purchase-orders', [
        'supplier_id' => $supplierIdB,
        'lines' => [['product_id' => $productIdB, 'qty_ordered' => 10]],
    ]);
    $orderIdB = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($ownerA)->get("/purchase-orders/{$orderIdB}")->assertNotFound();
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/purchase-orders')->assertRedirect('/login');
});
