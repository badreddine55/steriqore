<?php

namespace App\Domain\Sterilization\Data;

use App\Domain\Sterilization\Models\Cycle;
use Spatie\LaravelData\Data;

class CycleData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $device_id,
        public readonly ?string $device_program_id,
        public readonly string $operator_id,
        public readonly int $cycle_number,
        public readonly string $status,
        public readonly ?string $started_at,
        public readonly ?string $completed_at,
        public readonly ?string $notes,
    ) {}

    public static function fromModel(Cycle $cycle): self
    {
        return new self(
            $cycle->id,
            $cycle->device_id,
            $cycle->device_program_id,
            $cycle->operator_id,
            $cycle->cycle_number,
            $cycle->status->value,
            $cycle->started_at?->toIso8601String(),
            $cycle->completed_at?->toIso8601String(),
            $cycle->notes,
        );
    }
}
