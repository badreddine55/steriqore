<?php

namespace App\Domain\Prosthetics\Data;

use Spatie\LaravelData\Data;

class ProstheticDashboardData extends Data
{
    public function __construct(
        public int $active_cases,
        public string $active_cases_delta,
        public int $at_laboratory,
        public string $at_laboratory_delta,
        public int $returned_to_practice,
        public string $returned_to_practice_delta,
        public int $waiting_for_placement,
        public string $waiting_for_placement_delta,
        public int $placements_today,
        public int $placements_this_week,
        public int $payments_to_verify,
        public string $payments_to_verify_delta,
        public int $relance_count,
        public int $upcoming_placements_count,
        public array $recent_returned_cases,
        public array $upcoming_placements,
    ) {}
}
