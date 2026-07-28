<?php

namespace App\Http\Controllers\Web\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Data\BatchListEntryData;
use App\Domain\Inventory\Models\AlertSettings;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Data\OptionData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Batches have no create/update route anywhere in the domain — they only
 * come into existence as a side effect of ReceiveGoodsAction (UI-14), and
 * their only status transition (active -> quarantined) belongs to the
 * non-conformity/recall flow (UI-24), not here. This is a read-only
 * tenant-wide list, same shape as ControlTestController's logbook.
 */
class BatchController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Batch::class);

        $nearExpiryDays = AlertSettings::forTenant(Tenant::current())->near_expiry_days;

        $batches = QueryBuilder::for(Batch::class)
            ->allowedFilters(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('expiry', function ($query, $value) use ($nearExpiryDays): void {
                    match ($value) {
                        'expired' => $query->whereNotNull('expiry_date')->where('expiry_date', '<', now()->toDateString()),
                        'near_expiry' => $query->whereNotNull('expiry_date')
                            ->where('expiry_date', '>=', now()->toDateString())
                            ->where('expiry_date', '<=', now()->addDays($nearExpiryDays)->toDateString()),
                        default => null,
                    };
                }),
            )
            ->allowedSorts('expiry_date', 'received_at')
            ->defaultSort('expiry_date')
            // with()/withSum() placed after every spatie-QueryBuilder-only
            // call — see ControlTestController for why the order matters.
            ->with(['product', 'supplier'])
            ->withSum('stockLevels', 'quantity')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $products = Product::orderBy('name')->get()
            ->map(fn (Product $product) => new OptionData($product->id, $product->name));

        $suppliers = Supplier::orderBy('name')->get()
            ->map(fn (Supplier $supplier) => new OptionData($supplier->id, $supplier->name));

        return Inertia::render('inventory/batches/index', [
            'batches' => BatchListEntryData::collect($batches, CursorPaginatedDataCollection::class),
            'products' => OptionData::collect($products),
            'suppliers' => OptionData::collect($suppliers),
            'filters' => $request->input('filter', []),
        ]);
    }
}
