<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\StockLevel;
use Spatie\LaravelData\Data;

class StockLevelData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $batch_id,
        public readonly string $location_id,
        public readonly int $quantity,
    ) {}

    public static function fromModel(StockLevel $level): self
    {
        return new self($level->id, $level->batch_id, $level->location_id, $level->quantity);
    }
}
