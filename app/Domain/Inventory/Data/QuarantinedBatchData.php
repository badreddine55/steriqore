<?php

namespace App\Domain\Inventory\Data;

use App\Domain\Compliance\Models\AuditEvent;
use App\Domain\Inventory\Models\Batch;
use Spatie\LaravelData\Data;

/**
 * The compliance-facing view of a quarantined batch (UI-24) — the "who,
 * why, when" comes from the append-only audit_events row
 * RaiseNonConformityAction writes (action=batch.quarantined) when it
 * quarantines a batch, not a new field on Batch itself. There is
 * deliberately no un-quarantine action anywhere in the domain yet
 * (ResolveNonConformityAction's own docblock: that needs its own
 * deliberate follow-up once someone has verified the batch is safe) —
 * this DTO backs a read-only screen, not a write one.
 */
class QuarantinedBatchData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $batch_number,
        public readonly string $product_id,
        public readonly string $product_name,
        public readonly ?string $supplier_name,
        public readonly int $qty_on_hand,
        public readonly ?string $expiry_date,
        public readonly ?string $quarantined_at,
        public readonly ?string $reason,
        public readonly ?string $quarantined_by,
    ) {}

    public static function fromModel(Batch $batch, ?AuditEvent $quarantineEvent): self
    {
        return new self(
            $batch->id,
            $batch->batch_number,
            $batch->product_id,
            $batch->product->name,
            $batch->supplier?->name,
            (int) ($batch->stock_levels_sum_quantity ?? 0),
            $batch->expiry_date?->toDateString(),
            $quarantineEvent?->occurred_at->toIso8601String(),
            $quarantineEvent?->reason,
            $quarantineEvent?->actor_label_snapshot,
        );
    }
}
