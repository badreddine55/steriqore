<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Catalog\Models\Product;
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $category_id,
        public readonly ?string $default_location_id,
        public readonly string $name,
        public readonly string $reference,
        public readonly string $unit,
        public readonly int $min_threshold,
        public readonly bool $is_sterilizable,
        public readonly ?string $barcode,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            $product->id,
            $product->category_id,
            $product->default_location_id,
            $product->name,
            $product->reference,
            $product->unit,
            $product->min_threshold,
            $product->is_sterilizable,
            $product->barcode,
        );
    }
}
