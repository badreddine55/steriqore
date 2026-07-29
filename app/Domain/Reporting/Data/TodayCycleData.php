<?php

namespace App\Domain\Reporting\Data;

use Spatie\LaravelData\Data;

/** One row of "today at a glance" (UI-30) — a cycle started today, in the practice's own timezone. */
class TodayCycleData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly int $cycle_number,
        public readonly string $device_name,
        public readonly string $status,
        public readonly ?string $release_decision,
    ) {}
}
