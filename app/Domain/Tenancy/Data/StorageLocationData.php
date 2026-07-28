<?php

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\StorageLocation;
use Spatie\LaravelData\Data;

class StorageLocationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $site_id,
        public readonly ?string $room_id,
        public readonly ?string $parent_location_id,
        public readonly string $name,
        public readonly ?string $kind,
        public readonly ?string $archived_at,
        public readonly int $units_on_hand,
    ) {}

    public static function fromModel(StorageLocation $location): self
    {
        return new self(
            $location->id,
            $location->site_id,
            $location->room_id,
            $location->parent_location_id,
            $location->name,
            $location->kind,
            $location->deleted_at?->toIso8601String(),
            $location->units_on_hand ?? 0,
        );
    }
}
