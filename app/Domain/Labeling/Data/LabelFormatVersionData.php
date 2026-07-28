<?php

namespace App\Domain\Labeling\Data;

use App\Domain\Labeling\Models\LabelFormatVersion;
use Spatie\LaravelData\Data;

class LabelFormatVersionData extends Data
{
    public function __construct(
        public readonly string $primary_code,
        public readonly string $sheet_layout,
        public readonly bool $show_batch_number,
        public readonly bool $show_product_name,
        public readonly string $effective_from,
        public readonly ?string $updated_by,
        public readonly string $reason,
    ) {}

    public static function fromModel(LabelFormatVersion $version): self
    {
        $version->loadMissing('actor');

        return new self(
            $version->primary_code->value,
            $version->sheet_layout->value,
            $version->show_batch_number,
            $version->show_product_name,
            $version->effective_from->toIso8601String(),
            $version->actor?->name,
            $version->reason,
        );
    }
}
