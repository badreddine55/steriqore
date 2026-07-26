<?php

namespace App\Domain\Labeling\Data;

use App\Domain\Labeling\Models\Label;
use Spatie\LaravelData\Data;

/**
 * Field names deliberately match the retired LabelScanMockController's
 * shape (label_id, cycle_number, device_name, sterilized_at, use_by_date,
 * sequence_in_cycle, site_name) so the mobile app built against the mock
 * keeps working. Types differ from the mock's hand-written demo values
 * where the real domain disagrees (cycle_number is this app's actual
 * per-device integer, not a composite string) — an intentional,
 * documented drift, not a silent one.
 */
class LabelScanResultData extends Data
{
    public function __construct(
        public readonly string $label_id,
        public readonly string $status,
        public readonly int $cycle_number,
        public readonly string $device_name,
        public readonly string $sterilized_at,
        public readonly string $use_by_date,
        public readonly int $sequence_in_cycle,
        public readonly string $site_name,
    ) {}

    public static function fromLabel(Label $label): self
    {
        $item = $label->cycleItem;
        $cycle = $item->cycle;

        return new self(
            $label->id,
            $label->status->value,
            $cycle->cycle_number,
            $cycle->device->name,
            $label->sterilized_at->toIso8601String(),
            $label->use_by_date->toDateString(),
            $item->sequence_in_cycle,
            $cycle->device->site->name,
        );
    }
}
