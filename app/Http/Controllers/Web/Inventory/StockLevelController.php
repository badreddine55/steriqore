<?php

namespace App\Http\Controllers\Web\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Data\StockLevelEntryData;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Http\Controllers\Controller;
use App\Support\Data\OptionData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * stock_levels is a projection over stock_movements (CLAUDE.md invariant
 * #1) — this screen only ever reads it, same as the mobile API's
 * StockLevelController@index. No web form writes stock directly; issue/
 * transfer/adjust stay mobile-only for now (out of this task's scope).
 */
class StockLevelController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockLevel::class);

        $levels = QueryBuilder::for(StockLevel::class)
            ->allowedFilters(
                AllowedFilter::exact('location_id'),
                AllowedFilter::callback('product_id', function ($query, $value): void {
                    $query->whereHas('batch', fn ($q) => $q->where('product_id', $value));
                }),
                AllowedFilter::callback('in_stock_only', function ($query, $value): void {
                    if ($value) {
                        $query->where('quantity', '>', 0);
                    }
                }),
            )
            ->allowedSorts('quantity')
            ->defaultSort('-quantity')
            ->with(['batch.product', 'location'])
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $products = Product::orderBy('name')->get()
            ->map(fn (Product $product) => new OptionData($product->id, $product->name));

        $locations = StorageLocation::orderBy('name')->get()
            ->map(fn (StorageLocation $location) => new OptionData($location->id, $location->name));

        return Inertia::render('inventory/stock/index', [
            'levels' => StockLevelEntryData::collect($levels, CursorPaginatedDataCollection::class),
            'products' => OptionData::collect($products),
            'locations' => OptionData::collect($locations),
            'filters' => $request->input('filter', []),
        ]);
    }
}
