<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\StockMovement;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class StockMovementData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $batch_id,
        public readonly string $location_id,
        public readonly int $qty,
        public readonly ?string $group_id,
        public readonly ?string $reason,
        public readonly CarbonInterface $occurred_at,
    ) {}

    public static function fromModel(StockMovement $movement): self
    {
        return new self(
            $movement->id,
            $movement->type->value,
            $movement->batch_id,
            $movement->location_id,
            $movement->qty,
            $movement->group_id,
            $movement->reason,
            $movement->occurred_at,
        );
    }
}
