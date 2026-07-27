<?php

namespace App\Support\Data;

use Spatie\LaravelData\Data;

/**
 * A generic {id, label} pair for filter dropdowns and pickers — every
 * screen with a "choose a device/operator/supplier/…" control reuses this
 * rather than a near-identical bespoke DTO per domain.
 */
class OptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
    ) {}
}
