<?php

namespace App\Domain\Traceability\Data;

use App\Domain\Traceability\Models\Patient;
use Spatie\LaravelData\Data;

class PatientData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $reference,
    ) {}

    public static function fromModel(Patient $patient): self
    {
        return new self($patient->id, $patient->reference);
    }
}
