<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Purchasing\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class PurchaseOrderData extends Data
{
    /**
     * @param  DataCollection<int, PurchaseOrderLineData>  $lines
     */
    public function __construct(
        public readonly string $id,
        public readonly string $supplier_id,
        public readonly string $status,
        public readonly ?CarbonInterface $ordered_at,
        public readonly ?CarbonInterface $expected_at,
        public readonly DataCollection $lines,
    ) {}

    public static function fromModel(PurchaseOrder $purchaseOrder): self
    {
        return new self(
            $purchaseOrder->id,
            $purchaseOrder->supplier_id,
            $purchaseOrder->status->value,
            $purchaseOrder->ordered_at,
            $purchaseOrder->expected_at,
            PurchaseOrderLineData::collect($purchaseOrder->lines, DataCollection::class),
        );
    }
}
