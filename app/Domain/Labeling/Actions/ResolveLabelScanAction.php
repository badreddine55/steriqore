<?php

namespace App\Domain\Labeling\Actions;

use App\Domain\Compliance\Actions\RecordAuditEventAction;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Models\User;
use App\Support\Api\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CLAUDE.md invariant #5: "Scanning a recalled/expired label returns 410
 * with a blocking code + audit event." Voided isn't named in that
 * invariant but gets the same treatment — it's the same kind of "this
 * physical label must not be trusted" state. Expiry is lazy: nothing else
 * in the codebase transitions Printed -> Expired yet, so this is where
 * that transition actually happens, the first time anyone scans it past
 * its use-by date.
 */
class ResolveLabelScanAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(string $code, User $actor): Label
    {
        $labelId = self::extractLabelId($code);

        // Postgres errors on an invalid UUID literal rather than matching
        // zero rows — a garbage/unknown code must 404 cleanly, not 500.
        $label = Str::isUuid($labelId)
            ? Label::with(['cycleItem.cycle.device.site'])->find($labelId)
            : null;

        if ($label === null) {
            throw new ApiException('LABEL_NOT_FOUND', 'No label matches this code.', 404);
        }

        if ($label->status === LabelStatus::Recalled) {
            $this->blockScan($label, $actor, 'label.scan_blocked_recalled');
            throw new ApiException('LABEL_RECALLED', 'This label has been recalled and must not be used.', 410);
        }

        if ($label->status === LabelStatus::Voided) {
            $this->blockScan($label, $actor, 'label.scan_blocked_voided');
            throw new ApiException('LABEL_VOIDED', 'This label has been voided and must not be used.', 410);
        }

        if ($label->status !== LabelStatus::Expired && $label->use_by_date->isPast()) {
            $label->update(['status' => LabelStatus::Expired]);
        }

        if ($label->status === LabelStatus::Expired) {
            $this->blockScan($label, $actor, 'label.scan_blocked_expired');
            throw new ApiException('LABEL_EXPIRED', 'This label is past its use-by date.', 410);
        }

        if ($label->status === LabelStatus::Printed) {
            $label->update(['status' => LabelStatus::Used]);
            $this->recordAuditEvent->execute(
                tenant: $label->tenant,
                actor: $actor,
                action: 'label.used',
                subjectType: Label::class,
                subjectId: $label->id,
                request: $this->request,
            );
        }

        return $label;
    }

    private function blockScan(Label $label, User $actor, string $action): void
    {
        $this->recordAuditEvent->execute(
            tenant: $label->tenant,
            actor: $actor,
            action: $action,
            subjectType: Label::class,
            subjectId: $label->id,
            request: $this->request,
        );
    }

    private static function extractLabelId(string $code): string
    {
        $prefix = 'urn:steriqore:label:';

        return Str::startsWith($code, $prefix) ? Str::after($code, $prefix) : $code;
    }
}
