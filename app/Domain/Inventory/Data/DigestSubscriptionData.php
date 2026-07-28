<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Inventory\Models\DigestSubscription;
use Spatie\LaravelData\Data;

class DigestSubscriptionData extends Data
{
    public function __construct(
        public readonly string $role,
        public readonly string $frequency,
        public readonly bool $enabled,
        public readonly ?string $last_sent_at,
    ) {}

    public static function fromModel(DigestSubscription $subscription): self
    {
        return new self(
            $subscription->role,
            $subscription->frequency,
            $subscription->enabled,
            $subscription->last_sent_at?->toIso8601String(),
        );
    }
}
