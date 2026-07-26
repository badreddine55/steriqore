<?php

namespace App\Domain\Sterilization\Data;

use App\Domain\Sterilization\Models\ControlTest;
use Spatie\LaravelData\Data;

class ControlTestData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $cycle_id,
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
            $test->type->value,
            $test->result->value,
            $test->performed_at->toIso8601String(),
            $test->notes,
        );
    }
}
