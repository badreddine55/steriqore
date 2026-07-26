<?php

namespace App\Domain\Labeling\Data;

use App\Domain\Labeling\Models\Label;
use Spatie\LaravelData\Data;

class LabelData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $urn,
        public readonly string $cycle_item_id,
        public readonly string $status,
        public readonly string $packaging_type,
        public readonly string $storage_condition,
        public readonly int $shelf_life_days,
        public readonly string $sterilized_at,
        public readonly string $use_by_date,
        public readonly int $print_counter,
    ) {}

    public static function fromModel(Label $label): self
    {
        return new self(
            $label->id,
            $label->urn(),
            $label->cycle_item_id,
            $label->status->value,
            $label->packaging_type,
            $label->storage_condition,
            $label->shelf_life_days,
            $label->sterilized_at->toIso8601String(),
            $label->use_by_date->toDateString(),
            $label->print_counter,
        );
    }
}
