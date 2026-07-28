<?php

namespace App\Domain\Labeling\Data;

use App\Domain\Labeling\Models\DluRule;
use App\Domain\Labeling\Models\Label;
use Spatie\LaravelData\Data;

class DluRuleData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $packaging_type,
        public readonly string $storage_condition,
        public readonly int $shelf_life_days,
        public readonly ?string $last_updated_at,
        public readonly ?string $last_updated_by,
        public readonly ?string $last_reason,
        public readonly int $existing_labels_count,
    ) {}

    public static function fromModel(DluRule $rule): self
    {
        $rule->loadMissing('currentVersion.actor');

        return new self(
            $rule->id,
            $rule->packaging_type,
            $rule->storage_condition,
            $rule->shelf_life_days,
            $rule->currentVersion?->effective_from?->toIso8601String(),
            $rule->currentVersion?->actor?->name,
            $rule->currentVersion?->reason,
            Label::where('packaging_type', $rule->packaging_type)
                ->where('storage_condition', $rule->storage_condition)
                ->count(),
        );
    }
}
