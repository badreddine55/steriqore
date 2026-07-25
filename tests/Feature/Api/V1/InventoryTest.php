<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function invTenantWithMember(TenantRole $role = TenantRole::StockManager): array
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

function invTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

function invLocationFor(Tenant $tenant, string $name = 'Réserve'): string
{
    $site = $tenant->sites()->create(['name' => 'Cabinet Principal '.Str::random(4)]);

    return $site->storageLocations()->create(['tenant_id' => $tenant->id, 'name' => $name])->id;
}

/**
 * Receives 100 units of a fresh product/batch into $locationId via the
 * real purchasing flow (S2-ANA-03), so stock_movements/stock_levels are
 * populated exactly the way they would be in practice.
 */
function receiveStockFor(string $token, string $locationId, int $qty = 100): array
{
    $supplierId = test()->withToken($token)->postJson('/api/v1/suppliers', [
        'name' => 'Supplier '.Str::random(6),
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $productId = test()->withToken($token)->postJson('/api/v1/products', [
        'name' => 'Product', 'reference' => 'REF-'.Str::random(8),
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $po = test()->withToken($token)->postJson('/api/v1/purchase-orders', [
        'supplier_id' => $supplierId,
        'lines' => [['product_id' => $productId, 'qty_ordered' => $qty]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $poId = $po->json('id');
    $lineId = $po->json('lines.0.id');

    test()->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/order", [], ['Idempotency-Key' => (string) Str::uuid()]);

    $receipt = test()->withToken($token)->postJson("/api/v1/purchase-orders/{$poId}/receipts", [
        'location_id' => $locationId,
        'lines' => [['purchase_order_line_id' => $lineId, 'batch_number' => 'B-'.Str::random(6), 'qty' => $qty]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    return ['batch_id' => $receipt->json('lines.0.batch_id'), 'product_id' => $productId];
}

test('receiving stock credits stock_levels at the receiving location', function () {
    [$tenant, $user] = invTenantWithMember();
    $token = invTokenFor($tenant, $user);
    $locationId = invLocationFor($tenant);

    $received = receiveStockFor($token, $locationId, 100);

    $levels = $this->withToken($token)->getJson('/api/v1/stock-levels');
    $levels->assertOk();
    $level = collect($levels->json('data'))->firstWhere('batch_id', $received['batch_id']);
    expect($level['quantity'])->toBe(100);
});

test('issuing stock debits the location and cannot overdraw it', function () {
    [$tenant, $user] = invTenantWithMember();
    $token = invTokenFor($tenant, $user);
    $locationId = invLocationFor($tenant);
    $received = receiveStockFor($token, $locationId, 100);

    $issue = $this->withToken($token)->postJson('/api/v1/stock-movements/issue', [
        'batch_id' => $received['batch_id'],
        'location_id' => $locationId,
        'qty' => 30,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $issue->assertCreated()->assertJsonPath('qty', -30);

    $levels = $this->withToken($token)->getJson("/api/v1/stock-levels?filter[batch_id]={$received['batch_id']}");
    expect($levels->json('data.0.quantity'))->toBe(70);

    $overdraw = $this->withToken($token)->postJson('/api/v1/stock-movements/issue', [
        'batch_id' => $received['batch_id'],
        'location_id' => $locationId,
        'qty' => 1000,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $overdraw->assertStatus(409)->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
});

test('transferring stock debits the source and credits the destination', function () {
    [$tenant, $user] = invTenantWithMember();
    $token = invTokenFor($tenant, $user);
    $locationA = invLocationFor($tenant, 'A');
    $locationB = invLocationFor($tenant, 'B');
    $received = receiveStockFor($token, $locationA, 100);

    $transfer = $this->withToken($token)->postJson('/api/v1/stock-movements/transfer', [
        'batch_id' => $received['batch_id'],
        'from_location_id' => $locationA,
        'to_location_id' => $locationB,
        'qty' => 40,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $transfer->assertCreated();

    $levels = $this->withToken($token)->getJson("/api/v1/stock-levels?filter[batch_id]={$received['batch_id']}");
    $byLocation = collect($levels->json('data'))->keyBy('location_id');
    expect($byLocation[$locationA]['quantity'])->toBe(60);
    expect($byLocation[$locationB]['quantity'])->toBe(40);
});

test('adjusting stock requires a reason and applies a signed correction', function () {
    [$tenant, $user] = invTenantWithMember();
    $token = invTokenFor($tenant, $user);
    $locationId = invLocationFor($tenant);
    $received = receiveStockFor($token, $locationId, 100);

    $missingReason = $this->withToken($token)->postJson('/api/v1/stock-movements/adjust', [
        'batch_id' => $received['batch_id'],
        'location_id' => $locationId,
        'qty' => -5,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $missingReason->assertStatus(422);

    $adjust = $this->withToken($token)->postJson('/api/v1/stock-movements/adjust', [
        'batch_id' => $received['batch_id'],
        'location_id' => $locationId,
        'qty' => -5,
        'reason' => 'Damaged during a count',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $adjust->assertCreated()->assertJsonPath('qty', -5);

    $levels = $this->withToken($token)->getJson("/api/v1/stock-levels?filter[batch_id]={$received['batch_id']}");
    expect($levels->json('data.0.quantity'))->toBe(95);
});

test('stock_levels can be fully rebuilt from stock_movements and matches what it had before', function () {
    [$tenant, $user] = invTenantWithMember();
    $token = invTokenFor($tenant, $user);
    $locationId = invLocationFor($tenant);
    $received = receiveStockFor($token, $locationId, 100);
    $this->withToken($token)->postJson('/api/v1/stock-movements/issue', [
        'batch_id' => $received['batch_id'], 'location_id' => $locationId, 'qty' => 20,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $before = $this->withToken($token)->getJson("/api/v1/stock-levels?filter[batch_id]={$received['batch_id']}")
        ->json('data.0.quantity');

    $rebuild = $this->withToken($token)->postJson('/api/v1/stock-levels/rebuild', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $rebuild->assertOk();

    $after = $this->withToken($token)->getJson("/api/v1/stock-levels?filter[batch_id]={$received['batch_id']}")
        ->json('data.0.quantity');

    expect($after)->toBe($before)->toBe(80);
});

test('a viewer can read stock levels but cannot issue stock (vertical escalation)', function () {
    [$tenant, $manager] = invTenantWithMember(TenantRole::StockManager);
    $managerToken = invTokenFor($tenant, $manager);
    $locationId = invLocationFor($tenant);
    $received = receiveStockFor($managerToken, $locationId, 100);

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = invTokenFor($tenant, $viewer);

    $this->withToken($viewerToken)->getJson('/api/v1/stock-levels')->assertOk();
    $this->withToken($viewerToken)->postJson('/api/v1/stock-movements/issue', [
        'batch_id' => $received['batch_id'], 'location_id' => $locationId, 'qty' => 1,
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never reaches another practice stock levels, even by guessing a batch id (IDOR)', function () {
    [$tenantA, $userA] = invTenantWithMember();
    [$tenantB, $userB] = invTenantWithMember();
    $tokenA = invTokenFor($tenantA, $userA);
    $tokenB = invTokenFor($tenantB, $userB);
    $locationB = invLocationFor($tenantB);
    $receivedB = receiveStockFor($tokenB, $locationB, 100);

    $response = $this->withToken($tokenA)->getJson("/api/v1/stock-levels?filter[batch_id]={$receivedB['batch_id']}");

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});
