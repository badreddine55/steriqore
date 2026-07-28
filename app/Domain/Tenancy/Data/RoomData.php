<?php

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\Room;
use Spatie\LaravelData\Data;

class RoomData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $site_id,
        public readonly string $name,
        public readonly ?string $kind,
        public readonly ?string $archived_at,
        public readonly ?int $storage_locations_count,
    ) {}

    public static function fromModel(Room $room): self
    {
        return new self(
            $room->id,
            $room->site_id,
            $room->name,
            $room->kind,
            $room->deleted_at?->toIso8601String(),
            $room->storage_locations_count ?? null,
        );
    }
}
