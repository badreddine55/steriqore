<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\StockLevel;
use Spatie\LaravelData\Data;

/**
 * The tenant-wide "what's on the shelf" view (UI-16) — StockLevelData
 * (mobile API) has no reason to know product/batch/location names, it's
 * keyed by id for a client that already has that context. This is a
 * second, read-only shape over the exact same stock_levels projection.
 */
class StockLevelEntryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $batch_id,
        public readonly string $batch_number,
        public readonly string $product_id,
        public readonly string $product_name,
        public readonly string $location_id,
        public readonly string $location_name,
        public readonly int $quantity,
    ) {}

    public static function fromModel(StockLevel $level): self
    {
        return new self(
            $level->id,
            $level->batch_id,
            $level->batch->batch_number,
            $level->batch->product_id,
            $level->batch->product->name,
            $level->location_id,
            $level->location->name,
            $level->quantity,
        );
    }
}
