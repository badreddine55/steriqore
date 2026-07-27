<?php

namespace App\Domain\Traceability\Data;

use App\Domain\Traceability\Models\LabelUsage;
use Spatie\LaravelData\Data;

/**
 * The patient detail page's procedure history (UI-19) — LabelUsageData
 * (per-label, mobile API) has no reason to know the practitioner's name,
 * a client that already has that id doesn't need it. This is a second,
 * read-only shape over the same label_usages rows, linking out to the
 * label detail page (UI-07) for the full chain rather than repeating it.
 */
class PatientUsageEntryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $label_id,
        public readonly string $practitioner_name,
        public readonly string $procedure,
        public readonly string $used_at,
        public readonly ?string $notes,
    ) {}

    public static function fromModel(LabelUsage $usage): self
    {
        return new self(
            $usage->id,
            $usage->label_id,
            $usage->practitioner->name,
            $usage->procedure,
            $usage->used_at->toIso8601String(),
            $usage->notes,
        );
    }
}
