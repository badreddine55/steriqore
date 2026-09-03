<?php

namespace App\Domain\Prosthetics\Data;

use App\Domain\Prosthetics\Models\Laboratory;
use Spatie\LaravelData\Data;

class LaboratoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
        public ?string $contact_person,
        public ?array $specialties,
        public int $active_cases_count = 0,
    ) {}

    public static function fromModel(Laboratory $lab, int $activeCasesCount = 0): self
    {
        return new self(
            id: $lab->id,
            name: $lab->name,
            phone: $lab->phone,
            email: $lab->email,
            address: $lab->address,
            contact_person: $lab->contact_person,
            specialties: $lab->specialties ?? [],
            active_cases_count: $activeCasesCount,
        );
    }
}
