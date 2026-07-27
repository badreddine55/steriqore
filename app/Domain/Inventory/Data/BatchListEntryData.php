<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\Batch;
use Spatie\LaravelData\Data;

/**
 * The tenant-wide batches + expiries list (UI-15). Batches have no
 * dedicated creation route — they only ever come into existence as a
 * side effect of ReceiveGoodsAction (UI-14) — so this DTO backs a
 * read-only screen, not a CRUD one.
 */
class BatchListEntryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $product_id,
        public readonly string $product_name,
        public readonly ?string $supplier_id,
        public readonly ?string $supplier_name,
        public readonly string $batch_number,
        public readonly ?string $expiry_date,
        public readonly string $received_at,
        public readonly string $status,
        public readonly int $qty_on_hand,
    ) {}

    public static function fromModel(Batch $batch): self
    {
        return new self(
            $batch->id,
            $batch->product_id,
            $batch->product->name,
            $batch->supplier_id,
            $batch->supplier?->name,
            $batch->batch_number,
            $batch->expiry_date?->toDateString(),
            $batch->received_at->toIso8601String(),
            $batch->status->value,
            (int) ($batch->stock_levels_sum_quantity ?? 0),
        );
    }
}
