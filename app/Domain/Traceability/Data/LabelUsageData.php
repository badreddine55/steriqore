<?php

namespace App\Domain\Traceability\Data;

use App\Domain\Traceability\Models\LabelUsage;
use Spatie\LaravelData\Data;

class LabelUsageData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $label_id,
        public readonly string $patient_id,
        public readonly string $practitioner_id,
        public readonly string $procedure,
        public readonly string $used_at,
        public readonly ?string $notes,
    ) {}

    public static function fromModel(LabelUsage $usage): self
    {
        return new self(
            $usage->id,
            $usage->label_id,
            $usage->patient_id,
            $usage->practitioner_id,
            $usage->procedure,
            $usage->used_at->toIso8601String(),
            $usage->notes,
        );
    }
}
