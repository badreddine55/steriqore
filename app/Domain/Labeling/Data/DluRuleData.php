<?php

namespace App\Domain\Labeling\Data;

use App\Domain\Labeling\Models\DluRule;
use Spatie\LaravelData\Data;

class DluRuleData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $packaging_type,
        public readonly string $storage_condition,
        public readonly int $shelf_life_days,
    ) {}

    public static function fromModel(DluRule $rule): self
    {
        return new self(
            $rule->id,
            $rule->packaging_type,
            $rule->storage_condition,
            $rule->shelf_life_days,
        );
    }
}
