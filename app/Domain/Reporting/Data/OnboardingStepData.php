<?php

namespace App\Domain\Reporting\Data;

use Spatie\LaravelData\Data;

/**
 * One step of the new-practice checklist (UI-30) — shown instead of the
 * attention queue when a tenant has no devices and no cycles yet.
 * `can_act` is per-viewer (a practitioner sees the same ticked-off steps
 * an owner does, just without a live link for steps they can't perform).
 */
class OnboardingStepData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $done,
        public readonly bool $can_act,
    ) {}
}
