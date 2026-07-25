<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Tenancy\Models\Tenant;
use App\Support\Api\ApiException;

class CreateProductCategoryAction
{
    public function execute(Tenant $tenant, string $name, ?string $parentCategoryId): ProductCategory
    {
        if (ProductCategory::where('name', $name)->exists()) {
            throw new ApiException('CATEGORY_NAME_TAKEN', 'A category with this name already exists.', 409);
        }

        return ProductCategory::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'parent_category_id' => $parentCategoryId,
        ]);
    }
}
