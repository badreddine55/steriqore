<?php

namespace App\Domain\Reporting\Data;

use Spatie\LaravelData\Data;

class DashboardData extends Data
{
    /**
     * @param  OnboardingStepData[]  $onboarding_steps
     * @param  AttentionCardData[]  $attention_cards
     * @param  TodayCycleData[]  $today_cycles
     * @param  RecentProcedureData[]  $my_recent_procedures
     */
    public function __construct(
        public readonly bool $is_new_practice,
        public readonly array $onboarding_steps,
        public readonly array $attention_cards,
        public readonly array $today_cycles,
        public readonly bool $today_cycles_error,
        public readonly array $my_recent_procedures,
        public readonly bool $my_recent_procedures_error,
    ) {}
}
