<?php

namespace App\Domain\Equipment\Data;

use App\Domain\Equipment\Models\Device;
use Spatie\LaravelData\Data;

class DeviceData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $site_id,
        public readonly string $name,
        public readonly string $serial_number,
        public readonly string $kind,
        public readonly string $status,
        public readonly ?string $manufacturer,
        public readonly ?string $model,
        public readonly ?string $commissioned_at,
        public readonly ?string $decommissioned_at,
        public readonly ?string $notes,
    ) {}

    public static function fromModel(Device $device): self
    {
        return new self(
            $device->id,
            $device->site_id,
            $device->name,
            $device->serial_number,
            $device->kind->value,
            $device->status->value,
            $device->manufacturer,
            $device->model,
            $device->commissioned_at?->toDateString(),
            $device->decommissioned_at?->toDateString(),
            $device->notes,
        );
    }
}
