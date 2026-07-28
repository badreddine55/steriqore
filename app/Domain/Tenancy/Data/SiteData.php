<?php

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\Site;
use Spatie\LaravelData\Data;

class SiteData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $address_line1,
        public readonly ?string $address_line2,
        public readonly ?string $postal_code,
        public readonly ?string $city,
        public readonly ?string $country_code,
        public readonly string $timezone,
        public readonly bool $is_primary,
        public readonly ?string $archived_at,
        public readonly ?int $rooms_count,
        public readonly ?int $storage_locations_count,
        public readonly ?int $devices_count,
    ) {}

    public static function fromModel(Site $site): self
    {
        return new self(
            $site->id,
            $site->name,
            $site->address_line1,
            $site->address_line2,
            $site->postal_code,
            $site->city,
            $site->country_code,
            $site->timezone,
            $site->is_primary,
            $site->deleted_at?->toIso8601String(),
            $site->rooms_count ?? null,
            $site->storage_locations_count ?? null,
            $site->devices_count ?? null,
        );
    }
}
