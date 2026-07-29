<?php

namespace App\Domain\Reporting\Data;

use Spatie\LaravelData\Data;

/**
 * One attention-queue row (UI-30) — `key` is the only thing the frontend
 * switches on to build the filtered-list link and pick an icon; `count`
 * always comes from the exact same query the target list screen filters
 * with (see BuildDashboardAction), never a re-implemented aggregate.
 */
class AttentionCardData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $count,
        public readonly bool $error,
    ) {}
}
