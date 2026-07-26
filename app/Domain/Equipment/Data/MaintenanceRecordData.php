<?php

namespace App\Domain\Equipment\Data;

use App\Domain\Equipment\Models\MaintenanceRecord;
use Spatie\LaravelData\Data;

class MaintenanceRecordData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $device_id,
        public readonly ?string $recorded_by_user_id,
        public readonly string $kind,
        public readonly ?string $technician,
        public readonly string $performed_at,
        public readonly ?string $next_due_at,
        public readonly ?string $description,
    ) {}

    public static function fromModel(MaintenanceRecord $record): self
    {
        return new self(
            $record->id,
            $record->device_id,
            $record->recorded_by_user_id,
            $record->kind->value,
            $record->technician,
            $record->performed_at->toIso8601String(),
            $record->next_due_at?->toIso8601String(),
            $record->description,
        );
    }
}
