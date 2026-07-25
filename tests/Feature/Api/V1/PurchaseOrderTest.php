<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function poTenantWithMember(TenantRole $role = TenantRole::StockManager): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);
    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

function poTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

function createSupplierAndProduct(string $token): array
{
    $supplierId = test()->withToken($token)->postJson('/api/v1/suppliers', [
        'name' => 'MedSupply SAS',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $productId = test()->withToken($token)->postJson('/api/v1/products', [
        'name' => 'Gloves', 'reference' => 'GLV-'.Str::random(6),
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    return [$supplierId, $productId];
}

test('a stock manager can create a purchase order, mark it ordered, and fully receive it', function () {
    [$tenant, $user] = poTenantWithMember();
    $token = poTokenFor($tenant, $user);
    [$supplierId, $productId] = createSupplierAndProduct($token);

    $create = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 100, 'unit_price' => 0.10]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()->assertJsonPath('status', 'draft');
    $poId = $create->json('id');
    $lineId = $create->json('lines.0.id');

    $order = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/order", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $order->assertCreated()->assertJsonPath('status', 'ordered');

    $receipt = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/receipts", [
        'lines' => [[
            'purchase_order_line_id' => $lineId,
            'batch_number' => 'BATCH-001',
            'expiry_date' => now()->addYear()->toDateString(),
            'qty' => 100,
        ]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $receipt->assertCreated()->assertJsonPath('lines.0.qty', 100);

    $show = $this->withToken($token)->getJson("/api/v1/purchase-orders/{$poId}");
    $show->assertOk()->assertJsonPath('status', 'received')->assertJsonPath('lines.0.qty_received', 100);

    // No current tenant is bound outside an HTTP request (only the `tenant`
    // middleware sets it), so TenantScope would fail closed here regardless.
    expect(
        Batch::withoutGlobalScope(TenantScope::class)
            ->where('batch_number', 'BATCH-001')->where('product_id', $productId)->exists()
    )->toBeTrue();
});

test('a partial receipt moves the purchase order to partially_received, and a second receipt completes it', function () {
    [$tenant, $user] = poTenantWithMember();
    $token = poTokenFor($tenant, $user);
    [$supplierId, $productId] = createSupplierAndProduct($token);

    $create = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 100]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $poId = $create->json('id');
    $lineId = $create->json('lines.0.id');

    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/order", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $firstReceipt = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/receipts", [
        'lines' => [['purchase_order_line_id' => $lineId, 'batch_number' => 'B1', 'qty' => 60]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $firstReceipt->assertCreated();

    $this->withToken($token)->getJson("/api/v1/purchase-orders/{$poId}")
        ->assertJsonPath('status', 'partially_received')
        ->assertJsonPath('lines.0.qty_received', 60);

    $secondReceipt = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/receipts", [
        'lines' => [['purchase_order_line_id' => $lineId, 'batch_number' => 'B2', 'qty' => 40]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $secondReceipt->assertCreated();

    $this->withToken($token)->getJson("/api/v1/purchase-orders/{$poId}")
        ->assertJsonPath('status', 'received')
        ->assertJsonPath('lines.0.qty_received', 100);
});

test('the same receipt request submitted twice with the same Idempotency-Key is applied only once', function () {
    [$tenant, $user] = poTenantWithMember();
    $token = poTokenFor($tenant, $user);
    [$supplierId, $productId] = createSupplierAndProduct($token);

    $create = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 100]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $poId = $create->json('id');
    $lineId = $create->json('lines.0.id');
    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/order", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $payload = ['lines' => [['purchase_order_line_id' => $lineId, 'batch_number' => 'B1', 'qty' => 50]]];
    $idempotencyKey = (string) Str::uuid();

    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/receipts", $payload, ['Idempotency-Key' => $idempotencyKey])
        ->assertCreated();
    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/receipts", $payload, ['Idempotency-Key' => $idempotencyKey])
        ->assertCreated();

    $this->withToken($token)->getJson("/api/v1/purchase-orders/{$poId}")
        ->assertJsonPath('lines.0.qty_received', 50);
});

test('receiving cannot happen while the purchase order is still a draft', function () {
    [$tenant, $user] = poTenantWithMember();
    $token = poTokenFor($tenant, $user);
    [$supplierId, $productId] = createSupplierAndProduct($token);

    $create = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 100]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $poId = $create->json('id');
    $lineId = $create->json('lines.0.id');

    $response = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/receipts", [
        'lines' => [['purchase_order_line_id' => $lineId, 'batch_number' => 'B1', 'qty' => 50]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
});

test('a draft or ordered purchase order can be cancelled with a reason', function () {
    [$tenant, $user] = poTenantWithMember();
    $token = poTokenFor($tenant, $user);
    [$supplierId, $productId] = createSupplierAndProduct($token);

    $poId = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 10]],
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $cancel = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/cancel", [
        'reason' => 'Ordered by mistake',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $cancel->assertCreated()->assertJsonPath('status', 'cancelled');
});

test('a viewer can read purchase orders but cannot create one (vertical escalation)', function () {
    [$tenant, $manager] = poTenantWithMember(TenantRole::StockManager);
    $managerToken = poTokenFor($tenant, $manager);
    [$supplierId, $productId] = createSupplierAndProduct($managerToken);

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = poTokenFor($tenant, $viewer);

    $this->withToken($viewerToken)->getJson('/api/v1/purchase-orders')->assertOk();
    $this->withToken($viewerToken)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => 5]],
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice purchase order, even by guessing an id (IDOR)', function () {
    [$tenantA, $userA] = poTenantWithMember();
    [$tenantB, $userB] = poTenantWithMember();
    $tokenA = poTokenFor($tenantA, $userA);
    $tokenB = poTokenFor($tenantB, $userB);
    [$supplierIdB, $productIdB] = createSupplierAndProduct($tokenB);

    $foreignPoId = $this->withToken($tokenB)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierIdB,
        'lines' => [['product_id' => $productIdB, 'qty_ordered' => 5]],
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/purchase-orders/{$foreignPoId}")->assertStatus(404);
});
