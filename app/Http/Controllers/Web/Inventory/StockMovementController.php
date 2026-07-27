<?php

namespace App\Http\Controllers\Web\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Data\StockMovementLogEntryData;
use App\Domain\Inventory\Models\StockMovement;
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
 * stock_movements is append-only (CLAUDE.md invariant #1 and a DB trigger
 * enforce it) — this is the tenant-wide, read-only ledger view proving
 * that in the UI: no edit/delete action exists anywhere on this screen.
 */
class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockMovement::class);

        $movements = QueryBuilder::for(StockMovement::class)
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('location_id'),
                AllowedFilter::callback('product_id', function ($query, $value): void {
                    $query->whereHas('batch', fn ($q) => $q->where('product_id', $value));
                }),
                AllowedFilter::callback('from', function ($query, $value): void {
                    $query->where('occurred_at', '>=', $value);
                }),
                AllowedFilter::callback('to', function ($query, $value): void {
                    $query->where('occurred_at', '<=', $value);
                }),
            )
            ->allowedSorts('occurred_at')
            ->defaultSort('-occurred_at')
            ->with(['batch.product', 'location', 'actor'])
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $products = Product::orderBy('name')->get()
            ->map(fn (Product $product) => new OptionData($product->id, $product->name));

        $locations = StorageLocation::orderBy('name')->get()
            ->map(fn (StorageLocation $location) => new OptionData($location->id, $location->name));

        return Inertia::render('inventory/stock/movements', [
            'movements' => StockMovementLogEntryData::collect($movements, CursorPaginatedDataCollection::class),
            'products' => OptionData::collect($products),
            'locations' => OptionData::collect($locations),
            'filters' => $request->input('filter', []),
        ]);
    }
}
