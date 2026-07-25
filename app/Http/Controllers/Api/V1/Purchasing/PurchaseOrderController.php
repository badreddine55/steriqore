<?php

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Domain\Purchasing\Actions\CancelPurchaseOrderAction;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\MarkPurchaseOrderOrderedAction;
use App\Domain\Purchasing\Data\PurchaseOrderData;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\CancelPurchaseOrderRequest;
use App\Http\Requests\Api\V1\Purchasing\CreatePurchaseOrderRequest;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;

class PurchaseOrderController extends Controller
{
    /**
     * @return CursorPaginatedDataCollection<int, PurchaseOrderData>
     */
    public function index(Request $request): CursorPaginatedDataCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::with('lines')
            ->orderByDesc('created_at')
            ->cursorPaginate(perPage: (int) $request->integer('limit', 20));

        return PurchaseOrderData::collect($orders, CursorPaginatedDataCollection::class);
    }

    public function store(CreatePurchaseOrderRequest $request, CreatePurchaseOrderAction $action): PurchaseOrderData
    {
        $this->authorize('create', PurchaseOrder::class);

        $supplier = Supplier::findOrFail($request->string('supplier_id')->toString());

        $purchaseOrder = $action->execute(Tenant::current(), $supplier, $request->user(), $request->input('lines'));

        return PurchaseOrderData::fromModel($purchaseOrder);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderData
    {
        $this->authorize('view', $purchaseOrder);

        return PurchaseOrderData::fromModel($purchaseOrder->load('lines'));
    }

    public function order(PurchaseOrder $purchaseOrder, Request $request, MarkPurchaseOrderOrderedAction $action): PurchaseOrderData
    {
        $this->authorize('update', $purchaseOrder);

        return PurchaseOrderData::fromModel($action->execute($purchaseOrder, $request->user())->load('lines'));
    }

    public function cancel(PurchaseOrder $purchaseOrder, CancelPurchaseOrderRequest $request, CancelPurchaseOrderAction $action): PurchaseOrderData
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder = $action->execute($purchaseOrder, $request->user(), $request->string('reason')->toString() ?: null);

        return PurchaseOrderData::fromModel($purchaseOrder->load('lines'));
    }
}
