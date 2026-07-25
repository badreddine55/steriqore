<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Catalog\Actions\CreateProductCategoryAction;
use App\Domain\Catalog\Data\ProductCategoryData;
use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\CreateProductCategoryRequest;

class ProductCategoryController extends Controller
{
    /**
     * @return array<int, ProductCategoryData>
     */
    public function index(): array
    {
        $this->authorize('viewAny', ProductCategory::class);

        return ProductCategory::orderBy('name')->get()
            ->map(fn (ProductCategory $category) => ProductCategoryData::fromModel($category))
            ->all();
    }

    public function store(CreateProductCategoryRequest $request, CreateProductCategoryAction $action): ProductCategoryData
    {
        $this->authorize('create', ProductCategory::class);

        $category = $action->execute(
            Tenant::current(),
            $request->string('name')->toString(),
            $request->string('parent_category_id')->toString() ?: null,
        );

        return ProductCategoryData::fromModel($category);
    }
}
