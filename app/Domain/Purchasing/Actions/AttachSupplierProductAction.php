<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\SupplierProduct;

/**
 * Links a product to a supplier reference (CLAUDE.md: "Product ↔ supplier
 * reference linked"). Idempotent by (supplier_id, product_id) — calling
 * again updates the existing link rather than erroring or duplicating.
 */
class AttachSupplierProductAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Supplier $supplier, Product $product, array $attributes): SupplierProduct
    {
        return SupplierProduct::updateOrCreate(
            ['tenant_id' => $supplier->tenant_id, 'supplier_id' => $supplier->id, 'product_id' => $product->id],
            $attributes,
        );
    }
}
