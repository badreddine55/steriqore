<?php

namespace App\Domain\Compliance\Actions;

use App\Domain\Compliance\Models\NonConformity;
use App\Domain\Inventory\Enums\BatchStatus;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Labeling\Enums\LabelStatus;
use App\Domain\Labeling\Models\Label;
use App\Domain\Sterilization\Models\Cycle;
use App\Models\User;
use App\Support\Dashboard\DashboardCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CLAUDE.md invariant #3: "Mistakes are corrected by a non_conformity +
 * recall, never by UPDATE." Never touches an evidence table (cycles,
 * releases, labels' own history, usages, movements, audit_events stay
 * exactly as they were) — only Label.status and Batch.status move,
 * which are mutable state-machine fields, not the evidence itself.
 */
class RaiseNonConformityAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
        private Request $request,
    ) {}

    public function execute(Cycle|Label $subject, User $actor, string $description): NonConformity
    {
        return DB::transaction(function () use ($subject, $actor, $description) {
            $nonConformity = NonConformity::create([
                'tenant_id' => $subject->tenant_id,
                'subject_type' => $subject::class,
                'subject_id' => $subject->id,
                'description' => $description,
                'raised_by_user_id' => $actor->id,
                'raised_at' => now(),
            ]);

            $labels = $this->affectedLabels($subject);

            foreach ($labels as $label) {
                if (in_array($label->status, [LabelStatus::Recalled, LabelStatus::Voided], true)) {
                    continue;
                }

                $label->update(['status' => LabelStatus::Recalled]);

                $this->recordAuditEvent->execute(
                    tenant: $subject->tenant,
                    actor: $actor,
                    action: 'label.recalled',
                    subjectType: Label::class,
                    subjectId: $label->id,
                    reason: $description,
                    request: $this->request,
                );

                $batch = $label->cycleItem->batch;
                if ($batch instanceof Batch && $batch->status !== BatchStatus::Quarantined) {
                    $batch->update(['status' => BatchStatus::Quarantined]);

                    $this->recordAuditEvent->execute(
                        tenant: $subject->tenant,
                        actor: $actor,
                        action: 'batch.quarantined',
                        subjectType: Batch::class,
                        subjectId: $batch->id,
                        reason: $description,
                        request: $this->request,
                    );
                }
            }

            $this->recordAuditEvent->execute(
                tenant: $subject->tenant,
                actor: $actor,
                action: 'non_conformity.raised',
                subjectType: NonConformity::class,
                subjectId: $nonConformity->id,
                reason: $description,
                newValues: ['subject_type' => $subject::class, 'subject_id' => $subject->id],
                request: $this->request,
            );

            DashboardCache::forget($subject->tenant);

            return $nonConformity;
        });
    }

    /**
     * Read-only — the blast radius a confirmation screen shows BEFORE
     * raising, so the same skip conditions execute() applies (already-
     * recalled/voided labels, already-quarantined batches don't count
     * twice) must stay in sync with the loop above if either changes.
     *
     * @return array{labels_to_recall: int, batches_to_quarantine: int}
     */
    public function previewBlastRadius(Cycle|Label $subject): array
    {
        $labelsToRecall = $this->affectedLabels($subject)
            ->reject(fn (Label $label) => in_array($label->status, [LabelStatus::Recalled, LabelStatus::Voided], true));

        $batchesToQuarantine = $labelsToRecall
            ->map(fn (Label $label) => $label->cycleItem->batch)
            ->filter(fn ($batch) => $batch instanceof Batch && $batch->status !== BatchStatus::Quarantined)
            ->unique('id');

        return [
            'labels_to_recall' => $labelsToRecall->count(),
            'batches_to_quarantine' => $batchesToQuarantine->count(),
        ];
    }

    /**
     * @return Collection<int, Label>
     */
    public function affectedLabels(Cycle|Label $subject): Collection
    {
        if ($subject instanceof Label) {
            return new Collection([$subject]);
        }

        return Label::whereIn('cycle_item_id', $subject->items()->pluck('id'))->get();
    }
}
