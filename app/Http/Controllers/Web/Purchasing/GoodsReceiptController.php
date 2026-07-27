<?php

namespace App\Http\Controllers\Web\Purchasing;

use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\ReceiveGoodsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GoodsReceiptController extends Controller
{
    public function store(PurchaseOrder $purchaseOrder, ReceiveGoodsRequest $request, ReceiveGoodsAction $action): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $location = StorageLocation::findOrFail($request->string('location_id')->toString());

        $action->execute($purchaseOrder, $location, $request->user(), $request->input('lines'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Goods received.']);

        return back();
    }
}
