<?php

namespace App\Domain\Labeling\Data;

use App\Domain\Labeling\Models\Label;
use Spatie\LaravelData\Data;

/**
 * LabelData (per-cycle sheet) has no reason to know which cycle/device it
 * belongs to — the label sheet already has that context. The tenant-wide
 * labels index (UI-07) needs both, so this is a second, read-only shape
 * over the exact same Label rows, not a new domain concept — the same
 * pattern as ControlTestLogEntryData for the control-test logbook.
 */
class LabelListEntryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly int $cycle_number,
        public readonly string $device_name,
        public readonly string $description,
        public readonly string $use_by_date,
        public readonly int $print_counter,
    ) {}

    public static function fromModel(Label $label): self
    {
        $cycleItem = $label->cycleItem;

        return new self(
            $label->id,
            $label->status->value,
            $cycleItem->cycle->cycle_number,
            $cycleItem->cycle->device->name,
            $cycleItem->description,
            $label->use_by_date->toDateString(),
            $label->print_counter,
        );
    }
}
