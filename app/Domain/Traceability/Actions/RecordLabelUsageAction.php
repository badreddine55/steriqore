<?php

namespace App\Domain\Traceability\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Traceability\Models\LabelUsage;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;

/**
 * The last link in "pouch -> cycle -> device -> practitioner -> procedure
 * -> pseudonymized patient" (CLAUDE.md). Requires the label to already be
 * Used (LabelScanController transitions Printed -> Used on scan) — the
 * chain is scan first, then link, per CLAUDE.md's own ordering. Recalled/
 * voided is checked explicitly too: normally impossible to reach here in
 * that state (the scan gate already blocks it), except a label recalled
 * -after-use (S4-ANA-05), which is exactly what the backlog's acceptance
 * criterion ("a recalled or rejected label cannot be used") is guarding.
 */
class RecordLabelUsageAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Label $label, User $actor, array $attributes): LabelUsage
    {
        if (in_array($label->status, [LabelStatus::Recalled, LabelStatus::Voided], true)) {
            throw new ApiException('LABEL_NOT_USABLE', 'A recalled or voided label cannot be linked to a procedure.', 409);
        }

        if ($label->status !== LabelStatus::Used) {
            throw new ApiException('LABEL_NOT_SCANNED', 'A label must be scanned before it can be linked to a procedure.', 409);
        }

        if ($label->usage()->exists()) {
            throw new ApiException('LABEL_USAGE_ALREADY_RECORDED', 'This label already has a recorded usage.', 409);
        }

        $usage = $label->usage()->create($attributes + [
            'tenant_id' => $label->tenant_id,
            'used_at' => $attributes['used_at'] ?? now(),
        ]);

        $this->recordAuditEvent->execute(
            tenant: $label->tenant,
            actor: $actor,
            action: 'label_usage.recorded',
            subjectType: LabelUsage::class,
            subjectId: $usage->id,
            newValues: $attributes,
            request: $this->request,
        );

        return $usage;
    }
}
