<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Labeling\Models\LabelPrint;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The first print needs no reason. Every print after that is a reprint and
 * CLAUDE.md invariant #4 applies: "A reprint requires a reason, increments
 * print_counter, writes a label_prints row" — enforced here via the
 * ReleaseCycleRequest-style required_if in PrintLabelRequest, keyed off
 * the label's current status rather than another field on the request.
 */
class PrintLabelAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Label $label, User $actor, ?string $reason): Label
    {
        if (in_array($label->status, [LabelStatus::Recalled, LabelStatus::Voided], true)) {
            throw new ApiException('LABEL_NOT_PRINTABLE', 'A recalled or voided label can no longer be printed.', 409);
        }

        return DB::transaction(function () use ($label, $actor, $reason) {
            $label = Label::where('id', $label->id)->lockForUpdate()->first();
            $isReprint = $label->status === LabelStatus::Printed;
            $sequence = $label->print_counter + 1;
            // UI-28: which label-format version is rendering THIS print —
            // a print-time concern (see LabelFormatVersion's docblock),
            // recorded per print event rather than frozen on the label.
            $tenant = Tenant::current();
            $formatVersion = $tenant ? UpdateLabelFormatAction::current($tenant) : null;

            LabelPrint::create([
                'tenant_id' => $label->tenant_id,
                'label_id' => $label->id,
                'printed_by_user_id' => $actor->id,
                'sequence' => $sequence,
                'reason' => $reason,
                'printed_at' => now(),
                'label_format_version_id' => $formatVersion?->id,
            ]);

            $label->update(['status' => LabelStatus::Printed, 'print_counter' => $sequence]);

            $this->recordAuditEvent->execute(
                tenant: $label->tenant,
                actor: $actor,
                action: $isReprint ? 'label.reprinted' : 'label.printed',
                subjectType: Label::class,
                subjectId: $label->id,
                reason: $reason,
                newValues: ['sequence' => $sequence],
                request: $this->request,
            );

            return $label;
        });
    }
}
