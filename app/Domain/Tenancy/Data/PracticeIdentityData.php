<?php

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\Tenant;
use Spatie\LaravelData\Data;

class PracticeIdentityData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $legal_name,
        public readonly ?string $address_line1,
        public readonly ?string $address_line2,
        public readonly ?string $postal_code,
        public readonly ?string $city,
        public readonly ?string $country_code,
        public readonly ?string $contact_email,
        public readonly ?string $contact_phone,
        public readonly string $timezone,
        public readonly string $locale,
        public readonly ?string $logo_url,
    ) {}

    public static function fromModel(Tenant $tenant): self
    {
        return new self(
            $tenant->id,
            $tenant->name,
            $tenant->legal_name,
            $tenant->address_line1,
            $tenant->address_line2,
            $tenant->postal_code,
            $tenant->city,
            $tenant->country_code,
            $tenant->contact_email,
            $tenant->contact_phone,
            $tenant->timezone,
            $tenant->locale,
            $tenant->getFirstMediaUrl('logo') ?: null,
        );
    }
}
