import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label as FieldLabel } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import nonConformities from '@/routes/non-conformities';
import type { NonConformityBlastRadius } from '@/types';

/**
 * The recall flow's confirmation step (UI-23) — shows the blast radius
 * BEFORE confirming, computed server-side by the exact same
 * RaiseNonConformityAction the confirm button then executes
 * (previewBlastRadius), so the numbers here can never drift from what
 * actually happens on submit.
 */
export function RecallDialog({
    subjectType,
    subjectId,
    subjectLabel,
    blastRadius,
}: {
    subjectType: 'cycle' | 'label';
    subjectId: string;
    subjectLabel: string;
    blastRadius: NonConformityBlastRadius;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        subject_type: subjectType,
        subject_id: subjectId,
        description: '',
    });

    function submit() {
        form.post(nonConformities.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    const labelCount = blastRadius.labels_to_recall;
    const batchCount = blastRadius.batches_to_quarantine;
    const consequence =
        labelCount === 0
            ? 'Raise non-conformity'
            : `Recall ${labelCount} label${labelCount === 1 ? '' : 's'}`;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="destructive">
                    Recall {subjectType}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Recall {subjectLabel}?</DialogTitle>
                    <DialogDescription>
                        {labelCount === 0 ? (
                            <>
                                No labels will be recalled — this{' '}
                                {subjectType} has none outstanding. This
                                still raises a permanent, audited
                                non-conformity record.
                            </>
                        ) : (
                            <>
                                This will recall{' '}
                                <strong>
                                    {labelCount} label
                                    {labelCount === 1 ? '' : 's'}
                                </strong>{' '}
                                and quarantine{' '}
                                <strong>
                                    {batchCount} batch
                                    {batchCount === 1 ? '' : 'es'}
                                </strong>
                                . This is permanent — corrected afterwards
                                only by resolving the non-conformity with a
                                note, never by editing history.
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <FieldLabel htmlFor="nc-description">Reason</FieldLabel>
                    <Textarea
                        id="nc-description"
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="What went wrong?"
                    />
                    {form.errors.description && (
                        <p className="text-sm text-destructive">
                            {form.errors.description}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={
                            form.processing || !form.data.description.trim()
                        }
                    >
                        {consequence}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
