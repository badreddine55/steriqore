<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Inventory\Enums\BatchStatus;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Batches have no create/update route anywhere in the domain — they only
 * ever come into existence as a side effect of ReceiveGoodsAction
 * (GoodsReceiptsTest). Created directly here via Eloquent, the same way
 * StorageLocation fixtures already are (storageLocationFor), purely as
 * fixture setup so this read-only list has rows to show without going
 * through a full order->receive HTTP round trip for every expiry variant.
 */
function batchFor(Tenant $tenant, string $productId, ?string $supplierId, array $overrides = []): string
{
    return TenantContext::run($tenant, function () use ($tenant, $productId, $supplierId, $overrides) {
        return Batch::create(array_merge([
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'batch_number' => 'BATCH-'.Str::random(6),
            'expiry_date' => now()->addYear(),
            'received_at' => now(),
            'status' => BatchStatus::Active,
        ], $overrides))->id;
    });
}

function stockLevelFor(Tenant $tenant, string $batchId, int $quantity): void
{
    TenantContext::run($tenant, function () use ($tenant, $batchId, $quantity) {
        $siteId = $tenant->sites()->create(['name' => 'Cabinet Principal'])->id;
        $locationId = StorageLocation::create([
            'tenant_id' => $tenant->id,
            'site_id' => $siteId,
            'name' => 'Main storeroom',
        ])->id;

        StockLevel::create([
            'tenant_id' => $tenant->id,
            'batch_id' => $batchId,
            'location_id' => $locationId,
            'quantity' => $quantity,
        ]);
    });
}

test('an owner sees a received batch in the tenant-wide list with its on-hand quantity', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    $batchId = batchFor($tenant, $productId, $supplierId, ['batch_number' => 'BATCH-XYZ']);
    stockLevelFor($tenant, $batchId, 7);

    $this->actingAs($owner)
        ->get('/batches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/batches/index')
            ->has('batches.data', 1)
            ->where('batches.data.0.batch_number', 'BATCH-XYZ')
            ->where('batches.data.0.qty_on_hand', 7)
            ->where('batches.data.0.status', 'active')
        );
});

test('batches can be filtered to only ones expiring soon', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    batchFor($tenant, $productId, $supplierId, ['batch_number' => 'FAR-FUTURE', 'expiry_date' => now()->addYear()]);
    batchFor($tenant, $productId, $supplierId, ['batch_number' => 'SOON', 'expiry_date' => now()->addDays(5)]);
    batchFor($tenant, $productId, $supplierId, ['batch_number' => 'ALREADY-EXPIRED', 'expiry_date' => now()->subDay()]);

    $this->actingAs($owner)
        ->get('/batches?filter[expiry]=near_expiry')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('batches.data', 1)
            ->where('batches.data.0.batch_number', 'SOON')
        );

    $this->actingAs($owner)
        ->get('/batches?filter[expiry]=expired')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('batches.data', 1)
            ->where('batches.data.0.batch_number', 'ALREADY-EXPIRED')
        );
});

test('batches can be filtered by status to find quarantined ones', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierId, $productId] = supplierAndProductFor($owner);
    batchFor($tenant, $productId, $supplierId, ['batch_number' => 'ACTIVE-ONE', 'status' => BatchStatus::Active]);
    batchFor($tenant, $productId, $supplierId, ['batch_number' => 'QUARANTINED-ONE', 'status' => BatchStatus::Quarantined]);

    $this->actingAs($owner)
        ->get('/batches?filter[status]=quarantined')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('batches.data', 1)
            ->where('batches.data.0.batch_number', 'QUARANTINED-ONE')
        );
});

test('a practice cannot see another practice batches in the list (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    [$supplierIdB, $productIdB] = supplierAndProductFor($ownerB);
    batchFor($tenantB, $productIdB, $supplierIdB);

    $this->actingAs($ownerA)
        ->get('/batches')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('batches.data', 0));
});

test('a user without inventory.view is refused the batches list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
    });

    $this->actingAs($owner->fresh())->get('/batches')->assertForbidden();
});
