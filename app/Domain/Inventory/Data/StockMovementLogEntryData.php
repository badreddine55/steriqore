<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\StockMovement;
use Spatie\LaravelData\Data;

/**
 * The tenant-wide, append-only movement ledger (UI-16). qty is already
 * signed at the source (RecordStockMovementAction) — receipts and
 * transfer-credits are positive, issues and transfer-debits negative, so
 * this DTO just carries it through, it doesn't compute direction.
 */
class StockMovementLogEntryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $batch_id,
        public readonly string $batch_number,
        public readonly string $product_id,
        public readonly string $product_name,
        public readonly string $location_id,
        public readonly string $location_name,
        public readonly int $qty,
        public readonly ?string $group_id,
        public readonly ?string $reason,
        public readonly ?string $actor_name,
        public readonly string $occurred_at,
    ) {}

    public static function fromModel(StockMovement $movement): self
    {
        return new self(
            $movement->id,
            $movement->type->value,
            $movement->batch_id,
            $movement->batch->batch_number,
            $movement->batch->product_id,
            $movement->batch->product->name,
            $movement->location_id,
            $movement->location->name,
            $movement->qty,
            $movement->group_id,
            $movement->reason,
            $movement->actor?->name,
            $movement->occurred_at->toIso8601String(),
        );
    }
}
