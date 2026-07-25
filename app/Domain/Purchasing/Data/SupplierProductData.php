<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Purchasing\Models\SupplierProduct;
use Spatie\LaravelData\Data;

class SupplierProductData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $supplier_id,
        public readonly string $product_id,
        public readonly ?string $supplier_reference,
        public readonly int $pack_size,
        public readonly ?string $price,
    ) {}

    public static function fromModel(SupplierProduct $supplierProduct): self
    {
        return new self(
            $supplierProduct->id,
            $supplierProduct->supplier_id,
            $supplierProduct->product_id,
            $supplierProduct->supplier_reference,
            $supplierProduct->pack_size,
            $supplierProduct->price,
        );
    }
}
