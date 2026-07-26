<?php

namespace App\Domain\Sterilization\Data;

use App\Domain\Sterilization\Models\CycleRelease;
use Spatie\LaravelData\Data;

class CycleReleaseData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $cycle_id,
        public readonly string $decision,
        public readonly string $released_by_user_id,
        public readonly string $released_at,
        public readonly ?string $reason,
    ) {}

    public static function fromModel(CycleRelease $release): self
    {
        return new self(
            $release->id,
            $release->cycle_id,
            $release->decision->value,
            $release->released_by_user_id,
            $release->released_at->toIso8601String(),
            $release->reason,
        );
    }
}
