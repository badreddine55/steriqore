<?php

namespace App\Http\Controllers\Web\Purchasing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Purchasing\Actions\CreateSupplierAction;
use App\Domain\Purchasing\Actions\DeleteSupplierAction;
use App\Domain\Purchasing\Actions\UpdateSupplierAction;
use App\Domain\Purchasing\Data\SupplierData;
use App\Domain\Purchasing\Data\SupplierProductData;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\SupplierProduct;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\CreateSupplierRequest;
use App\Http\Requests\Api\V1\Purchasing\UpdateSupplierRequest;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Same Supplier model and SupplierData DTO the mobile API uses
 * (Api\V1\Purchasing\SupplierController) — this is a second, web-only
 * presentation of the identical query, not new domain logic.
 */
class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = QueryBuilder::for(Supplier::class)
            ->allowedFilters(AllowedFilter::partial('name'))
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        return Inertia::render('purchasing/suppliers/index', [
            'suppliers' => SupplierData::collect($suppliers, CursorPaginatedDataCollection::class),
            'canCreate' => $request->user()->can('create', Supplier::class),
            'filters' => $request->input('filter', []),
        ]);
    }

    public function store(CreateSupplierRequest $request, CreateSupplierAction $action): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $action->execute(Tenant::current(), $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Supplier \"{$supplier->name}\" created."]);

        return redirect()->route('suppliers.show', $supplier);
    }

    public function show(Supplier $supplier, Request $request): Response
    {
        $this->authorize('view', $supplier);

        $products = Product::orderBy('name')->get()
            ->map(fn (Product $product) => new OptionData($product->id, $product->name));

        return Inertia::render('purchasing/suppliers/show', [
            'supplier' => SupplierData::fromModel($supplier),
            'supplierProducts' => SupplierProductData::collect(
                $supplier->supplierProducts()->get()
                    ->map(fn (SupplierProduct $sp) => SupplierProductData::fromModel($sp))
            ),
            'products' => OptionData::collect($products),
            'canManage' => $request->user()->can('update', $supplier),
            'canDelete' => $request->user()->can('delete', $supplier),
        ]);
    }

    public function update(Supplier $supplier, UpdateSupplierRequest $request, UpdateSupplierAction $action): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $action->execute($supplier, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Supplier updated.']);

        return back();
    }

    public function destroy(Supplier $supplier, Request $request, DeleteSupplierAction $action): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $action->execute($supplier, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Supplier \"{$supplier->name}\" removed."]);

        return redirect()->route('suppliers.index');
    }
}
