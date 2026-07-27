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
import labels from '@/routes/labels';
import type { Label } from '@/types';

export function ReprintLabelDialog({ label }: { label: Label }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    function submit() {
        form.post(labels.print(label.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

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
                <Button size="sm" variant="outline" className="no-print">
                    Reprint
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reprint this label</DialogTitle>
                    <DialogDescription>
                        Reprints are tracked — say why this one is needed
                        again.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <FieldLabel htmlFor="reprint-reason">Reason</FieldLabel>
                    <Textarea
                        id="reprint-reason"
                        value={form.data.reason}
                        onChange={(e) =>
                            form.setData('reason', e.target.value)
                        }
                        placeholder="e.g. Original label was damaged."
                    />
                    {form.errors.reason && (
                        <p className="text-sm text-destructive">
                            {form.errors.reason}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={form.processing || !form.data.reason.trim()}
                    >
                        Reprint
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
