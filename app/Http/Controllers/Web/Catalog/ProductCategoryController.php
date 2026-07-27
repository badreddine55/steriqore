<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Domain\Catalog\Actions\CreateProductCategoryAction;
use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\CreateProductCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Create + list only — that is the entire domain surface for categories
 * (Api\V1\Catalog\ProductCategoryController has no update/destroy either).
 */
class ProductCategoryController extends Controller
{
    public function store(CreateProductCategoryRequest $request, CreateProductCategoryAction $action): RedirectResponse
    {
        $this->authorize('create', ProductCategory::class);

        $category = $action->execute(
            Tenant::current(),
            $request->string('name')->toString(),
            $request->string('parent_category_id')->toString() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category \"{$category->name}\" created."]);

        return back();
    }
}
