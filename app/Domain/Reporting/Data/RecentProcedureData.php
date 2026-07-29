<?php

namespace App\Domain\Reporting\Data;

use Spatie\LaravelData\Data;

/** One of the current user's own recent procedures (UI-30, practitioner role). */
class RecentProcedureData extends Data
{
    public function __construct(
        public readonly string $label_id,
        public readonly string $procedure,
        public readonly string $used_at,
    ) {}
}
