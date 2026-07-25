<?php

namespace App\Domain\Compliance\Data;

use App\Domain\Compliance\Models\AuditEvent;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class AuditEventData extends Data
{
    /**
     * @param  array<string, mixed>|null  $old_values
     * @param  array<string, mixed>|null  $new_values
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $actor_label_snapshot,
        public readonly string $action,
        public readonly string $subject_type,
        public readonly string $subject_id,
        public readonly ?array $old_values,
        public readonly ?array $new_values,
        public readonly ?string $reason,
        public readonly CarbonInterface $occurred_at,
    ) {}

    public static function fromModel(AuditEvent $event): self
    {
        return new self(
            $event->id,
            $event->actor_label_snapshot,
            $event->action,
            $event->subject_type,
            $event->subject_id,
            $event->old_values,
            $event->new_values,
            $event->reason,
            $event->occurred_at,
        );
    }
}
