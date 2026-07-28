<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\AlertSettings;
use Spatie\LaravelData\Data;

class AlertSettingsData extends Data
{
    public function __construct(
        public readonly int $near_expiry_days,
        public readonly int $default_low_stock_threshold,
        public readonly ?int $control_test_frequency_days,
        public readonly ?int $control_test_overdue_tolerance_days,
        public readonly int $failed_cycle_escalation_hours,
        public readonly bool $digests_enabled,
    ) {}

    public static function fromModel(AlertSettings $settings): self
    {
        return new self(
            $settings->near_expiry_days,
            $settings->default_low_stock_threshold,
            $settings->control_test_frequency_days,
            $settings->control_test_overdue_tolerance_days,
            $settings->failed_cycle_escalation_hours,
            $settings->digests_enabled,
        );
    }
}
