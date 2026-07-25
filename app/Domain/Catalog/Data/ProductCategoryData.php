<?php

namespace App\Domain\Catalog\Data;

use App\Domain\Catalog\Models\ProductCategory;
use Spatie\LaravelData\Data;

class ProductCategoryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $parent_category_id,
        public readonly string $name,
    ) {}

    public static function fromModel(ProductCategory $category): self
    {
        return new self($category->id, $category->parent_category_id, $category->name);
    }
}
