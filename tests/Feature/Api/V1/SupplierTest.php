<?php

use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function purchasingTenantWithMember(TenantRole $role = TenantRole::StockManager): array
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

function purchasingTokenFor(Tenant $tenant, User $user): string
{
    return test()->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'email' => $user->email,
        'password' => 'correct-password',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('token');
}

test('a stock manager can create, read, update, and delete a supplier', function () {
    [$tenant, $user] = purchasingTenantWithMember();
    $token = purchasingTokenFor($tenant, $user);

    $create = $this->withToken($token)->postJson('/api/v1/suppliers', [
        'name' => 'MedSupply SAS', 'email' => 'contact@medsupply.example',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $create->assertCreated()->assertJsonPath('name', 'MedSupply SAS');
    $supplierId = $create->json('id');

    $this->withToken($token)->getJson("/api/v1/suppliers/{$supplierId}")
        ->assertOk()->assertJsonPath('email', 'contact@medsupply.example');

    $this->withToken($token)->patchJson("/api/v1/suppliers/{$supplierId}", ['phone' => '0102030405'])
        ->assertOk()->assertJsonPath('phone', '0102030405');

    $this->withToken($token)->deleteJson("/api/v1/suppliers/{$supplierId}")->assertNoContent();
    expect(
        Supplier::withoutGlobalScope(TenantScope::class)->withTrashed()->find($supplierId)->trashed()
    )->toBeTrue();
});

test('creating a supplier with a duplicate name is rejected', function () {
    [$tenant, $user] = purchasingTenantWithMember();
    $token = purchasingTokenFor($tenant, $user);

    $this->withToken($token)->postJson('/api/v1/suppliers', ['name' => 'Dup Supplier'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $response = $this->withToken($token)->postJson('/api/v1/suppliers', ['name' => 'Dup Supplier'], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(409)->assertJsonPath('error.code', 'SUPPLIER_NAME_TAKEN');
});

test('a viewer can read suppliers but cannot create one (vertical escalation)', function () {
    [$tenant, $user] = purchasingTenantWithMember(TenantRole::Viewer);
    $token = purchasingTokenFor($tenant, $user);

    $this->withToken($token)->getJson('/api/v1/suppliers')->assertOk();
    $this->withToken($token)->postJson('/api/v1/suppliers', ['name' => 'X'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403);
});

test('a practice never reaches another practice supplier, even by guessing an id (IDOR)', function () {
    [$tenantA, $userA] = purchasingTenantWithMember();
    [$tenantB, $userB] = purchasingTenantWithMember();
    $tokenA = purchasingTokenFor($tenantA, $userA);
    $tokenB = purchasingTokenFor($tenantB, $userB);

    $foreignSupplierId = $this->withToken($tokenB)->postJson('/api/v1/suppliers', [
        'name' => 'Secret Supplier',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $this->withToken($tokenA)->getJson("/api/v1/suppliers/{$foreignSupplierId}")->assertStatus(404);
});

test('a product can be linked to a supplier with a supplier reference, price, and pack size', function () {
    [$tenant, $user] = purchasingTenantWithMember();
    $token = purchasingTokenFor($tenant, $user);

    $supplierId = $this->withToken($token)->postJson('/api/v1/suppliers', [
        'name' => 'MedSupply SAS',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $productId = $this->withToken($token)->postJson('/api/v1/products', [
        'name' => 'Gloves', 'reference' => 'GLV-1',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $link = $this->withToken($token)->postJson("/api/v1/suppliers/{$supplierId}/products", [
        'product_id' => $productId,
        'supplier_reference' => 'MS-GLV-100',
        'pack_size' => 100,
        'price' => 12.5,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $link->assertCreated()
        ->assertJsonPath('supplier_reference', 'MS-GLV-100')
        ->assertJsonPath('pack_size', 100);

    $index = $this->withToken($token)->getJson("/api/v1/suppliers/{$supplierId}/products");
    $index->assertOk();
    expect(collect($index->json())->pluck('product_id'))->toContain($productId);
});

test('linking a foreign tenant product to a supplier is rejected by validation', function () {
    [$tenantA, $userA] = purchasingTenantWithMember();
    [$tenantB, $userB] = purchasingTenantWithMember();
    $tokenA = purchasingTokenFor($tenantA, $userA);
    $tokenB = purchasingTokenFor($tenantB, $userB);

    $supplierIdA = $this->withToken($tokenA)->postJson('/api/v1/suppliers', [
        'name' => 'Supplier A',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $foreignProductId = $this->withToken($tokenB)->postJson('/api/v1/products', [
        'name' => 'Foreign product', 'reference' => 'FP-1',
    ], ['Idempotency-Key' => (string) Str::uuid()])->json('id');

    $response = $this->withToken($tokenA)->postJson("/api/v1/suppliers/{$supplierIdA}/products", [
        'product_id' => $foreignProductId,
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertStatus(422);
});
