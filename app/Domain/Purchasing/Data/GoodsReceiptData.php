<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Purchasing\Models\GoodsReceipt;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class GoodsReceiptData extends Data
{
    /**
     * @param  DataCollection<int, GoodsReceiptLineData>  $lines
     */
    public function __construct(
        public readonly string $id,
        public readonly string $purchase_order_id,
        public readonly CarbonInterface $received_at,
        public readonly DataCollection $lines,
    ) {}

    public static function fromModel(GoodsReceipt $receipt): self
    {
        return new self(
            $receipt->id,
            $receipt->purchase_order_id,
            $receipt->received_at,
            GoodsReceiptLineData::collect($receipt->lines, DataCollection::class),
        );
    }
}
