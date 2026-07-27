<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Purchasing\Models\SupplierProduct;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can view the suppliers list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical'])->assertRedirect();

    $this->actingAs($owner)
        ->get('/suppliers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/index')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Acme Medical')
            ->where('canCreate', true)
        );
});

test('the suppliers list can be filtered by name to no matches', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $this->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical']);

    $this->actingAs($owner)
        ->get('/suppliers?filter[name]=Nonexistent')
        ->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 0));
});

test('a supplier can be created, viewed, and edited', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $create = $this->actingAs($owner)->post('/suppliers', [
        'name' => 'Acme Medical',
        'email' => 'orders@acme.test',
    ]);
    $create->assertRedirect();
    $supplierId = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($owner)
        ->get("/suppliers/{$supplierId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchasing/suppliers/show')
            ->where('supplier.name', 'Acme Medical')
            ->where('supplier.email', 'orders@acme.test')
            ->where('canManage', true)
            ->where('canDelete', true)
        );

    $this->actingAs($owner)->patch("/suppliers/{$supplierId}", [
        'phone' => '+33 1 23 45 67 89',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/suppliers/{$supplierId}")
        ->assertInertia(fn (Assert $page) => $page->where('supplier.phone', '+33 1 23 45 67 89'));
});

test('creating a supplier with a duplicate name is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical'])->assertRedirect();

    // ApiExceptionWebRenderer converts SUPPLIER_NAME_TAKEN into a normal
    // redirect-back, not the API's JSON envelope or a raw 500.
    $second = $this->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical']);
    $second->assertRedirect();
    $second->assertSessionHas('_flash');
});

test('a supplier can be deleted', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $create = $this->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical']);
    $supplierId = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($owner)->delete("/suppliers/{$supplierId}")->assertRedirect('/suppliers');

    $this->actingAs($owner)
        ->get('/suppliers')
        ->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 0));
});

test('a product can be linked to a supplier, and linking it again updates rather than duplicates', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $supplierCreate = $this->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical']);
    $supplierId = Str::afterLast($supplierCreate->headers->get('Location'), '/');
    $productCreate = $this->actingAs($owner)->post('/products', [
        'name' => 'Sterilization pouch 90x230', 'reference' => 'SKU-001',
    ]);
    $productId = Str::afterLast($productCreate->headers->get('Location'), '/');

    $this->actingAs($owner)->post("/suppliers/{$supplierId}/products", [
        'product_id' => $productId,
        'supplier_reference' => 'ACME-POUCH-001',
        'pack_size' => 200,
        'price' => '45.90',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/suppliers/{$supplierId}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('supplierProducts', 1)
            ->where('supplierProducts.0.supplier_reference', 'ACME-POUCH-001')
            ->where('supplierProducts.0.pack_size', 200)
        );

    $this->actingAs($owner)->post("/suppliers/{$supplierId}/products", [
        'product_id' => $productId,
        'supplier_reference' => 'ACME-POUCH-001-V2',
        'pack_size' => 100,
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/suppliers/{$supplierId}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('supplierProducts', 1)
            ->where('supplierProducts.0.supplier_reference', 'ACME-POUCH-001-V2')
            ->where('supplierProducts.0.pack_size', 100)
        );

    expect(SupplierProduct::withoutGlobalScope(TenantScope::class)->where('supplier_id', $supplierId)->count())->toBe(1);
});

test('a viewer cannot create, edit, or delete a supplier', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $create = $this->actingAs($owner)->post('/suppliers', ['name' => 'Acme Medical']);
    $supplierId = Str::afterLast($create->headers->get('Location'), '/');

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });
    $viewer = $owner->fresh();

    $this->actingAs($viewer)->post('/suppliers', ['name' => 'Other Co'])->assertForbidden();
    $this->actingAs($viewer)->patch("/suppliers/{$supplierId}", ['name' => 'x'])->assertForbidden();
    $this->actingAs($viewer)->delete("/suppliers/{$supplierId}")->assertForbidden();
});

test('a practice never sees another practice suppliers (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $this->actingAs($ownerB)->post('/suppliers', ['name' => 'Practice B supplier']);

    $this->actingAs($ownerA)
        ->get('/suppliers')
        ->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 0));
});

test('a practice cannot view another practice supplier detail (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $create = $this->actingAs($ownerB)->post('/suppliers', ['name' => 'Practice B supplier']);
    $supplierIdB = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($ownerA)->get("/suppliers/{$supplierIdB}")->assertNotFound();
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/suppliers')->assertRedirect('/login');
});
