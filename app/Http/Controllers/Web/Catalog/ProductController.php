<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\Actions\DeleteProductAction;
use App\Domain\Catalog\Actions\UpdateProductAction;
use App\Domain\Catalog\Data\ProductCategoryData;
use App\Domain\Catalog\Data\ProductData;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductCategory;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\CreateProductRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateProductRequest;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Same Product model and ProductData DTO the mobile API uses
 * (Api\V1\Catalog\ProductController) — this is a second, web-only
 * presentation of the identical query, not new domain logic.
 */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = QueryBuilder::for(Product::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('reference'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('is_sterilizable'),
            )
            ->allowedSorts('name', 'reference', 'created_at')
            ->defaultSort('name')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $categories = ProductCategory::orderBy('name')->get()
            ->map(fn (ProductCategory $category) => ProductCategoryData::fromModel($category));

        return Inertia::render('catalog/products/index', [
            'products' => ProductData::collect($products, CursorPaginatedDataCollection::class),
            'categories' => ProductCategoryData::collect($categories),
            'canCreate' => $request->user()->can('create', Product::class),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function store(CreateProductRequest $request, CreateProductAction $action): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $action->execute(Tenant::current(), $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product \"{$product->name}\" created."]);

        return redirect()->route('products.show', $product);
    }

    public function show(Product $product, Request $request): Response
    {
        $this->authorize('view', $product);

        $product->loadMissing('category');

        return Inertia::render('catalog/products/show', [
            'product' => ProductData::fromModel($product),
            'category' => $product->category
                ? new OptionData($product->category->id, $product->category->name)
                : null,
            'canManage' => $request->user()->can('update', $product),
            'canDelete' => $request->user()->can('delete', $product),
        ]);
    }

    public function update(Product $product, UpdateProductRequest $request, UpdateProductAction $action): RedirectResponse
    {
        $this->authorize('update', $product);

        $action->execute($product, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product updated.']);

        return back();
    }

    public function destroy(Product $product, Request $request, DeleteProductAction $action): RedirectResponse
    {
        $this->authorize('delete', $product);

        $action->execute($product, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product \"{$product->name}\" removed."]);

        return redirect()->route('products.index');
    }
}
