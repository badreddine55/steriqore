<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Catalog\Actions\CreateProductAction;
use App\Domain\Catalog\Actions\DeleteProductAction;
use App\Domain\Catalog\Actions\UpdateProductAction;
use App\Domain\Catalog\Data\ProductData;
use App\Domain\Catalog\Models\Product;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\CreateProductRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateProductRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, ProductData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
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
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return ProductData::collect($products, CursorPaginatedDataCollection::class);
    }

    public function store(CreateProductRequest $request, CreateProductAction $action): ProductData
    {
        $this->authorize('create', Product::class);

        $product = $action->execute(Tenant::current(), $request->user(), $request->validated());

        return ProductData::fromModel($product);
    }

    public function show(Product $product): ProductData
    {
        $this->authorize('view', $product);

        return ProductData::fromModel($product);
    }

    public function update(Product $product, UpdateProductRequest $request, UpdateProductAction $action): ProductData
    {
        $this->authorize('update', $product);

        return ProductData::fromModel($action->execute($product, $request->user(), $request->validated()));
    }

    public function destroy(Product $product, Request $request, DeleteProductAction $action): Response
    {
        $this->authorize('delete', $product);

        $action->execute($product, $request->user());

        return response()->noContent();
    }
}
