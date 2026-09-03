<?php

namespace App\Domain\Traceability\Data;

use App\Domain\Traceability\Models\Patient;
use Spatie\LaravelData\Data;

class PatientData extends Data
{
    /**
     * @param array<string>|null $allergies
     */
    public function __construct(
        public readonly string $id,
        public readonly string $reference,
        public readonly ?string $first_name = null,
        public readonly ?string $last_name = null,
        public readonly ?string $birth_date = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $nir = null,
        public readonly ?string $notes = null,
        public readonly ?array $allergies = null,
        public readonly ?string $created_at = null,
    ) {}

    public static function fromModel(Patient $patient): self
    {
        return new self(
            id: $patient->id,
            reference: $patient->reference,
            first_name: $patient->first_name,
            last_name: $patient->last_name,
            birth_date: $patient->birth_date?->format('Y-m-d'),
            email: $patient->email,
            phone: $patient->phone,
            nir: $patient->nir,
            notes: $patient->notes,
            allergies: $patient->allergies,
            created_at: $patient->created_at?->toIso8601String(),
        );
    }
}
