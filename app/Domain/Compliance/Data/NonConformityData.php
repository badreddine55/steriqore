<?php

namespace App\Domain\Compliance\Data;

use App\Domain\Compliance\Models\NonConformity;
use Spatie\LaravelData\Data;

class NonConformityData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $subject_type,
        public readonly string $subject_id,
        public readonly string $description,
        public readonly string $raised_by_user_id,
        public readonly string $raised_at,
        public readonly ?string $resolved_by_user_id,
        public readonly ?string $resolved_at,
        public readonly ?string $resolution,
    ) {}

    public static function fromModel(NonConformity $nonConformity): self
    {
        return new self(
            $nonConformity->id,
            class_basename($nonConformity->subject_type),
            $nonConformity->subject_id,
            $nonConformity->description,
            $nonConformity->raised_by_user_id,
            $nonConformity->raised_at->toIso8601String(),
            $nonConformity->resolved_by_user_id,
            $nonConformity->resolved_at?->toIso8601String(),
            $nonConformity->resolution,
        );
    }
}
