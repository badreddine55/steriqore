<?php

namespace App\Domain\Sterilization\Data;

use App\Domain\Sterilization\Models\CycleItem;
use Spatie\LaravelData\Data;

class CycleItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $cycle_id,
        public readonly ?string $batch_id,
        public readonly string $description,
        public readonly int $sequence_in_cycle,
    ) {}

    public static function fromModel(CycleItem $item): self
    {
        return new self(
            $item->id,
            $item->cycle_id,
            $item->batch_id,
            $item->description,
            $item->sequence_in_cycle,
        );
    }
}
