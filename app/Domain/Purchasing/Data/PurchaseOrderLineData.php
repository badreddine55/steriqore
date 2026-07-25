<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Purchasing\Models\PurchaseOrderLine;
use Spatie\LaravelData\Data;

class PurchaseOrderLineData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $product_id,
        public readonly int $qty_ordered,
        public readonly int $qty_received,
        public readonly ?string $unit_price,
    ) {}

    public static function fromModel(PurchaseOrderLine $line): self
    {
        return new self($line->id, $line->product_id, $line->qty_ordered, $line->qty_received, $line->unit_price);
    }
}
