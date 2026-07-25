<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Purchasing\Models\GoodsReceiptLine;
use Spatie\LaravelData\Data;

class GoodsReceiptLineData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $purchase_order_line_id,
        public readonly string $product_id,
        public readonly string $batch_id,
        public readonly int $qty,
        public readonly ?string $discrepancy_reason,
    ) {}

    public static function fromModel(GoodsReceiptLine $line): self
    {
        return new self(
            $line->id,
            $line->purchase_order_line_id,
            $line->product_id,
            $line->batch_id,
            $line->qty,
            $line->discrepancy_reason,
        );
    }
}
