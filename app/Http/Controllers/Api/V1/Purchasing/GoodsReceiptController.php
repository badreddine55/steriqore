<?php

namespace App\Http\Controllers\Api\V1\Purchasing;

use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Data\GoodsReceiptData;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchasing\ReceiveGoodsRequest;

class GoodsReceiptController extends Controller
{
    public function store(PurchaseOrder $purchaseOrder, ReceiveGoodsRequest $request, ReceiveGoodsAction $action): GoodsReceiptData
    {
        $this->authorize('update', $purchaseOrder);

        $location = StorageLocation::findOrFail($request->string('location_id')->toString());

        $receipt = $action->execute($purchaseOrder, $location, $request->user(), $request->input('lines'));

        return GoodsReceiptData::fromModel($receipt);
    }
}
