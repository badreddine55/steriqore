<?php

namespace App\Domain\Sterilization\Data;

use App\Domain\Sterilization\Models\ControlTest;
use Spatie\LaravelData\Data;

/**
 * ControlTestData (per-cycle) has no reason to know about the cycle or
 * device it belongs to — the cycle detail page already has that context.
 * The tenant-wide logbook (UI-04) needs both, so this is a second,
 * read-only shape over the exact same ControlTest rows, not a new domain
 * concept.
 */
class ControlTestLogEntryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $cycle_id,
        public readonly int $cycle_number,
        public readonly string $device_id,
        public readonly string $device_name,
        public readonly string $type,
        public readonly string $result,
        public readonly string $performed_at,
        public readonly ?string $notes,
    ) {}

    public static function fromModel(ControlTest $test): self
    {
        return new self(
            $test->id,
            $test->cycle_id,
            $test->cycle->cycle_number,
            $test->cycle->device_id,
            $test->cycle->device->name,
            $test->type->value,
            $test->result->value,
            $test->performed_at->toIso8601String(),
            $test->notes,
        );
    }
}
