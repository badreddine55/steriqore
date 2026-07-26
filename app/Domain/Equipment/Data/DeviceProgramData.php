<?php

namespace App\Domain\Equipment\Data;

use App\Domain\Equipment\Models\DeviceProgram;
use Spatie\LaravelData\Data;

class DeviceProgramData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $device_id,
        public readonly string $name,
        public readonly int $target_temperature_celsius,
        public readonly int $plateau_minutes,
        public readonly bool $is_active,
    ) {}

    public static function fromModel(DeviceProgram $program): self
    {
        return new self(
            $program->id,
            $program->device_id,
            $program->name,
            $program->target_temperature_celsius,
            $program->plateau_minutes,
            $program->is_active,
        );
    }
}
