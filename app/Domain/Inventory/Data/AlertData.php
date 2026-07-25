<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\Alert;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class AlertData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $severity,
        public readonly string $state,
        public readonly string $subject_type,
        public readonly string $subject_id,
        public readonly string $message,
        public readonly ?CarbonInterface $resolved_at,
    ) {}

    public static function fromModel(Alert $alert): self
    {
        return new self(
            $alert->id,
            $alert->type->value,
            $alert->severity->value,
            $alert->state->value,
            $alert->subject_type,
            $alert->subject_id,
            $alert->message,
            $alert->resolved_at,
        );
    }
}
