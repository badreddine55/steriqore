<?php

namespace App\Domain\Labeling\Data;

use App\Domain\Labeling\Models\LabelPrint;
use Spatie\LaravelData\Data;

class LabelPrintData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $label_id,
        public readonly string $printed_by_user_id,
        public readonly int $sequence,
        public readonly ?string $reason,
        public readonly string $printed_at,
    ) {}

    public static function fromModel(LabelPrint $print): self
    {
        return new self(
            $print->id,
            $print->label_id,
            $print->printed_by_user_id,
            $print->sequence,
            $print->reason,
            $print->printed_at->toIso8601String(),
        );
    }
}
