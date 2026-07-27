<?php

use App\Domain\Identity\Enums\TenantRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can view the products list', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->post('/products', [
        'name' => 'Sterilization pouch 90x230',
        'reference' => 'SKU-001',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/products/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Sterilization pouch 90x230')
            ->where('canCreate', true)
        );
});

test('the products list can be filtered by name to no matches', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $this->actingAs($owner)->post('/products', [
        'name' => 'Sterilization pouch 90x230',
        'reference' => 'SKU-001',
    ]);

    $this->actingAs($owner)
        ->get('/products?filter[name]=Nonexistent')
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('a category can be created from the products page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->post('/product-categories', [
        'name' => 'Pouches',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get('/products')
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Pouches')
        );
});

test('a product can be created, viewed, and edited', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $create = $this->actingAs($owner)->post('/products', [
        'name' => 'Sterilization pouch 90x230',
        'reference' => 'SKU-001',
        'unit' => 'box',
        'is_sterilizable' => true,
    ]);
    $create->assertRedirect();
    $productId = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($owner)
        ->get("/products/{$productId}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/products/show')
            ->where('product.name', 'Sterilization pouch 90x230')
            ->where('product.unit', 'box')
            ->where('canManage', true)
            ->where('canDelete', true)
        );

    $this->actingAs($owner)->patch("/products/{$productId}", [
        'name' => 'Sterilization pouch 90x230 (updated)',
    ])->assertRedirect();

    $this->actingAs($owner)
        ->get("/products/{$productId}")
        ->assertInertia(fn (Assert $page) => $page->where('product.name', 'Sterilization pouch 90x230 (updated)'));
});

test('creating a product with a duplicate reference is blocked with a real message, not a broken page', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)->post('/products', [
        'name' => 'Product A',
        'reference' => 'SKU-DUP',
    ])->assertRedirect();

    // ApiExceptionWebRenderer converts PRODUCT_REFERENCE_TAKEN into a
    // normal redirect-back, not the API's JSON envelope or a raw 500.
    $second = $this->actingAs($owner)->post('/products', [
        'name' => 'Product B',
        'reference' => 'SKU-DUP',
    ]);
    $second->assertRedirect();
    $second->assertSessionHas('_flash');
});

test('a product can be deleted', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $create = $this->actingAs($owner)->post('/products', [
        'name' => 'Sterilization pouch 90x230',
        'reference' => 'SKU-001',
    ]);
    $productId = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($owner)->delete("/products/{$productId}")->assertRedirect('/products');

    $this->actingAs($owner)
        ->get('/products')
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('a viewer cannot create, edit, or delete a product', function () {
    [$tenant, $owner] = sterilizationTenantWithMember(TenantRole::Owner);
    $create = $this->actingAs($owner)->post('/products', [
        'name' => 'Sterilization pouch 90x230',
        'reference' => 'SKU-001',
    ]);
    $productId = Str::afterLast($create->headers->get('Location'), '/');

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });
    $viewer = $owner->fresh();

    $this->actingAs($viewer)->post('/products', [
        'name' => 'Product X',
        'reference' => 'SKU-X',
    ])->assertForbidden();
    $this->actingAs($viewer)->patch("/products/{$productId}", ['name' => 'x'])->assertForbidden();
    $this->actingAs($viewer)->delete("/products/{$productId}")->assertForbidden();
});

test('a practice never sees another practice products (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $this->actingAs($ownerB)->post('/products', [
        'name' => 'Practice B product',
        'reference' => 'SKU-B',
    ]);

    $this->actingAs($ownerA)
        ->get('/products')
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

test('a practice cannot view another practice product detail (IDOR)', function () {
    [, $ownerA] = sterilizationTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = sterilizationTenantWithMember(TenantRole::Owner);
    $create = $this->actingAs($ownerB)->post('/products', [
        'name' => 'Practice B product',
        'reference' => 'SKU-B',
    ]);
    $productIdB = Str::afterLast($create->headers->get('Location'), '/');

    $this->actingAs($ownerA)->get("/products/{$productIdB}")->assertNotFound();
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/products')->assertRedirect('/login');
});
