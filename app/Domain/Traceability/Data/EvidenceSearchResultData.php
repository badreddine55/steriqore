<?php

namespace App\Domain\Traceability\Data;

use App\Domain\Traceability\Models\LabelUsage;
use Spatie\LaravelData\Data;

/**
 * One row per label usage — the natural unit tying pouch -> cycle ->
 * device -> practitioner -> procedure -> patient together for inspection
 * (CLAUDE.md's core loop), and what S4-ANA-04's evidence dossier export
 * will page through.
 */
class EvidenceSearchResultData extends Data
{
    public function __construct(
        public readonly string $label_id,
        public readonly string $label_status,
        public readonly int $cycle_number,
        public readonly string $device_name,
        public readonly string $site_name,
        public readonly ?string $batch_number,
        public readonly string $patient_reference,
        public readonly string $practitioner_name,
        public readonly string $procedure,
        public readonly string $used_at,
    ) {}

    public static function fromModel(LabelUsage $usage): self
    {
        $cycleItem = $usage->label->cycleItem;
        $cycle = $cycleItem->cycle;

        return new self(
            $usage->label_id,
            $usage->label->status->value,
            $cycle->cycle_number,
            $cycle->device->name,
            $cycle->device->site->name,
            $cycleItem->batch?->batch_number,
            $usage->patient->reference,
            $usage->practitioner->name,
            $usage->procedure,
            $usage->used_at->toIso8601String(),
        );
    }
}
