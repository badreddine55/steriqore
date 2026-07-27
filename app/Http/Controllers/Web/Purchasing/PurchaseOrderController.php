<?php

namespace App\Http\Controllers\Web\Purchasing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Purchasing\Actions\CancelPurchaseOrderAction;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\MarkPurchaseOrderOrderedAction;
use App\Domain\Purchasing\Data\GoodsReceiptData;
use App\Domain\Purchasing\Data\PurchaseOrderData;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\CancelPurchaseOrderRequest;
use App\Http\Requests\Api\V1\Purchasing\CreatePurchaseOrderRequest;
use App\Support\Data\OptionData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\CursorPaginatedDataCollection;

/**
 * Same PurchaseOrder model and PurchaseOrderData DTO the mobile API uses
 * (Api\V1\Purchasing\PurchaseOrderController) — this is a second, web-only
 * presentation of the identical query, not new domain logic.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::with('lines')
            ->orderByDesc('created_at')
            ->cursorPaginate(perPage: 25)
            ->withQueryString();

        $suppliers = Supplier::orderBy('name')->get()
            ->map(fn (Supplier $supplier) => new OptionData($supplier->id, $supplier->name));

        return Inertia::render('purchasing/purchase-orders/index', [
            'purchaseOrders' => PurchaseOrderData::collect($orders, CursorPaginatedDataCollection::class),
            'suppliers' => OptionData::collect($suppliers),
            'canCreate' => $request->user()->can('create', PurchaseOrder::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        $suppliers = Supplier::orderBy('name')->get()
            ->map(fn (Supplier $supplier) => new OptionData($supplier->id, $supplier->name));
        $products = Product::orderBy('name')->get()
            ->map(fn (Product $product) => new OptionData($product->id, $product->name));

        return Inertia::render('purchasing/purchase-orders/create', [
            'suppliers' => OptionData::collect($suppliers),
            'products' => OptionData::collect($products),
        ]);
    }

    public function store(CreatePurchaseOrderRequest $request, CreatePurchaseOrderAction $action): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $supplier = Supplier::findOrFail($request->string('supplier_id')->toString());

        $purchaseOrder = $action->execute(Tenant::current(), $supplier, $request->user(), $request->input('lines'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order created.']);

        return redirect()->route('purchase-orders.show', $purchaseOrder);
    }

    public function show(PurchaseOrder $purchaseOrder, Request $request): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->loadMissing(['lines', 'supplier']);

        $products = Product::orderBy('name')->get()
            ->map(fn (Product $product) => new OptionData($product->id, $product->name));

        $locations = StorageLocation::orderBy('name')->get()
            ->map(fn (StorageLocation $location) => new OptionData($location->id, $location->name));

        $goodsReceipts = $purchaseOrder->goodsReceipts()->with('lines')->orderByDesc('received_at')->get()
            ->map(fn (GoodsReceipt $receipt) => GoodsReceiptData::fromModel($receipt));

        return Inertia::render('purchasing/purchase-orders/show', [
            'purchaseOrder' => PurchaseOrderData::fromModel($purchaseOrder),
            'supplier' => new OptionData($purchaseOrder->supplier->id, $purchaseOrder->supplier->name),
            'products' => OptionData::collect($products),
            'locations' => OptionData::collect($locations),
            'goodsReceipts' => GoodsReceiptData::collect($goodsReceipts),
            'canManage' => $request->user()->can('update', $purchaseOrder),
        ]);
    }

    public function order(PurchaseOrder $purchaseOrder, Request $request, MarkPurchaseOrderOrderedAction $action): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $action->execute($purchaseOrder, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order marked as ordered.']);

        return back();
    }

    public function cancel(PurchaseOrder $purchaseOrder, CancelPurchaseOrderRequest $request, CancelPurchaseOrderAction $action): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $action->execute($purchaseOrder, $request->user(), $request->string('reason')->toString() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchase order cancelled.']);

        return back();
    }
}
