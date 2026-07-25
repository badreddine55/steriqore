<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function catalogTenantWithMember(TenantRole $role = TenantRole::StockManager): array
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

function catalogTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

test('a stock manager can create, read, update, search, and delete a product', function () {
    [$tenant, $user] = catalogTenantWithMember();
    $token = catalogTokenFor($tenant, $user);

    $create = $this->withToken($token)->postJson('/api/v1/products', [
        'name' => 'Sterilization Pouch 90x260mm',
        'reference' => 'POU-90260',
        'unit' => 'box_of_200',
        'min_threshold' => 5,
        'barcode' => '1234567890123',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $create->assertCreated()->assertJsonPath('reference', 'POU-90260');
    $productId = $create->json('id');

    $this->withToken($token)->getJson("/api/v1/products/{$productId}")
        ->assertOk()->assertJsonPath('name', 'Sterilization Pouch 90x260mm');

    $search = $this->withToken($token)->getJson('/api/v1/products?filter[reference]=POU-90260');
    $search->assertOk();
    expect(collect($search->json('data'))->pluck('reference'))->toContain('POU-90260');

    $update = $this->withToken($token)->patchJson("/api/v1/products/{$productId}", ['min_threshold' => 10]);
    $update->assertOk()->assertJsonPath('min_threshold', 10);

    $this->withToken($token)->deleteJson("/api/v1/products/{$productId}")->assertNoContent();

    // Outside of an HTTP request there's no "current tenant" bound (that's
    // only set by the `tenant` middleware for the duration of a request),
    // so TenantScope would fail closed here regardless of soft-deletion —
    // bypass it explicitly to assert on soft-delete specifically.
    expect(Product::withoutGlobalScope(TenantScope::class)->find($productId))->toBeNull();
    expect(Product::withoutGlobalScope(TenantScope::class)->withTrashed()->find($productId))->not->toBeNull();
});

test('creating a product with a duplicate reference is rejected', function () {
    [$tenant, $user] = catalogTenantWithMember();
    $token = catalogTokenFor($tenant, $user);

    $this->withToken($token)->postJson('/api/v1/products', [
        'name' => 'A', 'reference' => 'DUP-1',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $response = $this->withToken($token)->postJson('/api/v1/products', [
        'name' => 'B', 'reference' => 'DUP-1',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'PRODUCT_REFERENCE_TAKEN');
});

test('a viewer can read products but cannot create one (vertical escalation)', function () {
    [$tenant, $user] = catalogTenantWithMember(TenantRole::Viewer);
    $token = catalogTokenFor($tenant, $user);

    $this->withToken($token)->getJson('/api/v1/products')->assertOk();

    $this->withToken($token)->postJson('/api/v1/products', [
        'name' => 'A', 'reference' => 'V-1',
    ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(403);
});

test('a practice never sees another practice products, even by guessing an id (IDOR)', function () {
    [$tenantA, $userA] = catalogTenantWithMember();
    [$tenantB, $userB] = catalogTenantWithMember();
    $tokenA = catalogTokenFor($tenantA, $userA);
    $tokenB = catalogTokenFor($tenantB, $userB);

    $foreignProduct = $this->withToken($tokenB)->postJson('/api/v1/products', [
        'name' => 'Secret Product', 'reference' => 'SECRET-1',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/products/{$foreignProduct}")->assertStatus(404);

    $index = $this->withToken($tokenA)->getJson('/api/v1/products');
    expect(collect($index->json('data'))->pluck('reference'))->not->toContain('SECRET-1');
});

test('a product can be created under a category, and category names must be unique per tenant', function () {
    [$tenant, $user] = catalogTenantWithMember();
    $token = catalogTokenFor($tenant, $user);

    $category = $this->withToken($token)->postJson('/api/v1/product-categories', [
        'name' => 'Consumables',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $category->assertCreated();
    $categoryId = $category->json('id');

    $product = $this->withToken($token)->postJson('/api/v1/products', [
        'name' => 'Gloves', 'reference' => 'GLV-1', 'category_id' => $categoryId,
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $product->assertCreated()->assertJsonPath('category_id', $categoryId);

    $dup = $this->withToken($token)->postJson('/api/v1/product-categories', [
        'name' => 'Consumables',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $dup->assertStatus(409)->assertJsonPath('error.code', 'CATEGORY_NAME_TAKEN');
});

test('assigning a foreign tenant category id to a product is rejected by validation', function () {
    [$tenantA, $userA] = catalogTenantWithMember();
    [$tenantB, $userB] = catalogTenantWithMember();
    $tokenA = catalogTokenFor($tenantA, $userA);
    $tokenB = catalogTokenFor($tenantB, $userB);

    $foreignCategoryId = $this->withToken($tokenB)->postJson('/api/v1/product-categories', [
        'name' => 'Foreign Category',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $response = $this->withToken($tokenA)->postJson('/api/v1/products', [
        'name' => 'X', 'reference' => 'X-1', 'category_id' => $foreignCategoryId,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(422);
});
