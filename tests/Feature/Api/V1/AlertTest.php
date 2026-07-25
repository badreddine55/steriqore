<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function alertTenantWithMember(TenantRole $role = TenantRole::StockManager): array
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

function alertTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

function alertLocationFor(Tenant $tenant): string
{
    $site = $tenant->sites()->create(['name' => 'Cabinet Principal']);

    return $site->storageLocations()->create(['tenant_id' => $tenant->id, 'name' => 'Réserve'])->id;
}

/**
 * Creates a product with the given threshold, receives $qty of it with the
 * given expiry (or none), via the real purchasing flow.
 */
/**
 * @return array{product_id: string, batch_id: string}
 */
function receiveProductWithThreshold(string $token, string $locationId, int $minThreshold, int $qty, ?string $expiryDate = null): array
{
    $supplierId = test()->withToken($token)->postJson('/api/v1/suppliers', [
        'name' => 'Supplier '.Str::random(6),
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $productId = test()->withToken($token)->postJson('/api/v1/products', [
        'name' => 'Product', 'reference' => 'REF-'.Str::random(8), 'min_threshold' => $minThreshold,
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
        'lines' => [[
            'purchase_order_line_id' => $lineId,
            'batch_number' => 'B-'.Str::random(6),
            'expiry_date' => $expiryDate,
            'qty' => $qty,
        ]],
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    return ['product_id' => $productId, 'batch_id' => $receipt->json('lines.0.batch_id')];
}

test('detecting alerts opens a low_stock alert when stock is below threshold', function () {
    [$tenant, $user] = alertTenantWithMember();
    $token = alertTokenFor($tenant, $user);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);

    $detect = $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $detect->assertOk()->assertJsonPath('low_stock', 1);

    $alerts = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=low_stock');
    $alerts->assertOk();
    expect($alerts->json('data'))->toHaveCount(1);
    expect($alerts->json('data.0.state'))->toBe('open');
});

test('detecting alerts is idempotent: running it twice does not duplicate the open alert', function () {
    [$tenant, $user] = alertTenantWithMember();
    $token = alertTokenFor($tenant, $user);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);

    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $alerts = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=low_stock');
    expect($alerts->json('data'))->toHaveCount(1);
});

test('restocking above threshold auto-resolves the low_stock alert on the next detection', function () {
    [$tenant, $user] = alertTenantWithMember();
    $token = alertTokenFor($tenant, $user);
    $locationId = alertLocationFor($tenant);
    $received = receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);

    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->withToken($token)->postJson('/api/v1/stock-movements/adjust', [
        'batch_id' => $received['batch_id'],
        'location_id' => $locationId,
        'qty' => 100,
        'reason' => 'Restocked',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $alerts = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=low_stock&filter[state]=open');
    expect($alerts->json('data'))->toBeEmpty();
});

test('a batch past its expiry date opens an expired alert instead of a near_expiry one', function () {
    [$tenant, $user] = alertTenantWithMember();
    $token = alertTokenFor($tenant, $user);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 0, qty: 10, expiryDate: now()->subDay()->toDateString());

    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $expired = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=expired');
    expect($expired->json('data'))->toHaveCount(1);

    $nearExpiry = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=near_expiry&filter[state]=open');
    expect($nearExpiry->json('data'))->toBeEmpty();
});

test('a batch expiring soon opens a near_expiry alert, and a batch far from expiry does not', function () {
    [$tenant, $user] = alertTenantWithMember();
    $token = alertTokenFor($tenant, $user);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 0, qty: 10, expiryDate: now()->addDays(10)->toDateString());
    receiveProductWithThreshold($token, $locationId, minThreshold: 0, qty: 10, expiryDate: now()->addYear()->toDateString());

    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $nearExpiry = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=near_expiry');
    expect($nearExpiry->json('data'))->toHaveCount(1);
});

test('an alert can be manually resolved and cannot be resolved twice', function () {
    [$tenant, $user] = alertTenantWithMember();
    $token = alertTokenFor($tenant, $user);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($token, $locationId, minThreshold: 50, qty: 10);
    $this->withToken($token)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);

    $alertId = $this->withToken($token)->getJson('/api/v1/alerts?filter[type]=low_stock')->json('data.0.id');

    $resolve = $this->withToken($token)->postJson("/api/v1/alerts/{$alertId}/resolve", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $resolve->assertCreated()->assertJsonPath('state', 'resolved');

    $again = $this->withToken($token)->postJson("/api/v1/alerts/{$alertId}/resolve", [], ['Idempotency-Key' => (string) Str::uuid()]);
    $again->assertStatus(409)->assertJsonPath('error.code', 'ALERT_ALREADY_RESOLVED');
});

test('a viewer can read alerts but cannot resolve one (vertical escalation)', function () {
    [$tenant, $manager] = alertTenantWithMember(TenantRole::StockManager);
    $managerToken = alertTokenFor($tenant, $manager);
    $locationId = alertLocationFor($tenant);
    receiveProductWithThreshold($managerToken, $locationId, minThreshold: 50, qty: 10);
    $this->withToken($managerToken)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $alertId = $this->withToken($managerToken)->getJson('/api/v1/alerts')->json('data.0.id');

    $viewer = User::create(['name' => 'V', 'email' => 'v-'.Str::lower(Str::random(8)).'@example.com', 'password' => Hash::make('correct-password')]);
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $viewer->id, 'status' => 'active']);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $viewer->assignRole(TenantRole::Viewer->value);
    $registrar->setPermissionsTeamId(null);
    $viewerToken = alertTokenFor($tenant, $viewer);

    $this->withToken($viewerToken)->getJson('/api/v1/alerts')->assertOk();
    $this->withToken($viewerToken)->postJson("/api/v1/alerts/{$alertId}/resolve", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403);
});

test('a practice never reaches another practice alerts (IDOR)', function () {
    [$tenantA, $userA] = alertTenantWithMember();
    [$tenantB, $userB] = alertTenantWithMember();
    $tokenA = alertTokenFor($tenantA, $userA);
    $tokenB = alertTokenFor($tenantB, $userB);
    $locationB = alertLocationFor($tenantB);
    receiveProductWithThreshold($tokenB, $locationB, minThreshold: 50, qty: 10);
    $this->withToken($tokenB)->postJson('/api/v1/alerts/detect', [], ['Idempotency-Key' => (string) Str::uuid()]);
    $foreignAlertId = $this->withToken($tokenB)->getJson('/api/v1/alerts')->json('data.0.id');

    $this->withToken($tokenA)->postJson("/api/v1/alerts/{$foreignAlertId}/resolve", [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(404);
});
