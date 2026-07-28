import { useForm } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label as FieldLabel } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import practiceSettings from '@/routes/practice-settings';
import type { DluRule } from '@/types';

function AddRuleDialog({ canManage }: { canManage: boolean }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        packaging_type: '',
        storage_condition: '',
        shelf_life_days: '',
        reason: '',
    });

    function submit() {
        form.post(practiceSettings.dluRules.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    if (!canManage) {
        return null;
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
                <Button size="sm">
                    <Plus className="size-4" />
                    Add rule
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a DLU rule</DialogTitle>
                    <DialogDescription>
                        Computes the use-by date for labels generated with
                        this packaging and storage combination.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="new-rule-packaging">
                                Packaging type
                            </FieldLabel>
                            <Input
                                id="new-rule-packaging"
                                value={form.data.packaging_type}
                                onChange={(e) =>
                                    form.setData(
                                        'packaging_type',
                                        e.target.value,
                                    )
                                }
                            />
                            {form.errors.packaging_type && (
                                <p className="text-sm text-destructive">
                                    {form.errors.packaging_type}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="new-rule-storage">
                                Storage condition
                            </FieldLabel>
                            <Input
                                id="new-rule-storage"
                                value={form.data.storage_condition}
                                onChange={(e) =>
                                    form.setData(
                                        'storage_condition',
                                        e.target.value,
                                    )
                                }
                            />
                            {form.errors.storage_condition && (
                                <p className="text-sm text-destructive">
                                    {form.errors.storage_condition}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="new-rule-shelf-life">
                            Shelf life (days)
                        </FieldLabel>
                        <Input
                            id="new-rule-shelf-life"
                            type="number"
                            min={1}
                            value={form.data.shelf_life_days}
                            onChange={(e) =>
                                form.setData(
                                    'shelf_life_days',
                                    e.target.value,
                                )
                            }
                        />
                        {form.errors.shelf_life_days && (
                            <p className="text-sm text-destructive">
                                {form.errors.shelf_life_days}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="new-rule-reason">
                            Reason
                        </FieldLabel>
                        <Textarea
                            id="new-rule-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            placeholder="Source for this shelf-life value?"
                        />
                        {form.errors.reason && (
                            <p className="text-sm text-destructive">
                                {form.errors.reason}
                            </p>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={
                            form.processing ||
                            !form.data.packaging_type.trim() ||
                            !form.data.storage_condition.trim() ||
                            !form.data.shelf_life_days ||
                            !form.data.reason.trim()
                        }
                    >
                        Add rule
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function EditRuleDialog({ rule }: { rule: DluRule }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        shelf_life_days: String(rule.shelf_life_days),
        reason: '',
    });

    function submit() {
        form.patch(practiceSettings.dluRules.update(rule.id).url, {
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
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="icon" variant="ghost" className="size-7">
                    <Pencil className="size-3.5" />
                    <span className="sr-only">
                        Edit {rule.packaging_type} / {rule.storage_condition}
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Edit {rule.packaging_type} / {rule.storage_condition}
                    </DialogTitle>
                    <DialogDescription>
                        New use-by rules apply to labels printed from now
                        on.{' '}
                        {rule.existing_labels_count > 0 ? (
                            <>
                                <strong>
                                    {rule.existing_labels_count} existing
                                    label
                                    {rule.existing_labels_count === 1
                                        ? ''
                                        : 's'}
                                </strong>{' '}
                                keep their current use-by dates — this
                                change is never applied retroactively.
                            </>
                        ) : (
                            'No labels have used this rule yet.'
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <FieldLabel htmlFor={`edit-rule-shelf-life-${rule.id}`}>
                            Shelf life (days)
                        </FieldLabel>
                        <Input
                            id={`edit-rule-shelf-life-${rule.id}`}
                            type="number"
                            min={1}
                            value={form.data.shelf_life_days}
                            onChange={(e) =>
                                form.setData(
                                    'shelf_life_days',
                                    e.target.value,
                                )
                            }
                        />
                        {form.errors.shelf_life_days && (
                            <p className="text-sm text-destructive">
                                {form.errors.shelf_life_days}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel htmlFor={`edit-rule-reason-${rule.id}`}>
                            Reason
                        </FieldLabel>
                        <Textarea
                            id={`edit-rule-reason-${rule.id}`}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        {form.errors.reason && (
                            <p className="text-sm text-destructive">
                                {form.errors.reason}
                            </p>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={
                            form.processing ||
                            !form.data.shelf_life_days ||
                            !form.data.reason.trim()
                        }
                    >
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function DluRuleSection({
    dluRules,
    canManage,
}: {
    dluRules: DluRule[];
    canManage: boolean;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-sm">DLU rule table</CardTitle>
                <AddRuleDialog canManage={canManage} />
            </CardHeader>
            <CardContent className="grid gap-4">
                <Alert>
                    <AlertTriangle />
                    <AlertTitle>Shelf-life values are unverified</AlertTitle>
                    <AlertDescription>
                        No EN 868 defaults are pre-filled — inventing
                        specific day counts here would mean guessing a
                        compliance figure. Every value below was entered by
                        this practice and has not been signed off by a
                        dental sterilization professional. The DLU is
                        frozen onto each label at print time, so editing a
                        rule never changes an existing label's use-by date.
                    </AlertDescription>
                </Alert>

                {!canManage && (
                    <p className="text-sm text-muted-foreground italic">
                        Only the practice owner can add or edit DLU rules.
                    </p>
                )}

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Packaging</TableHead>
                                <TableHead>Storage</TableHead>
                                <TableHead className="text-right">
                                    Shelf life
                                </TableHead>
                                <TableHead className="text-right">
                                    Labels using it
                                </TableHead>
                                <TableHead>Last updated</TableHead>
                                {canManage && (
                                    <TableHead className="no-print w-10" />
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {dluRules.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={canManage ? 6 : 5}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No DLU rules yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {dluRules.map((rule) => (
                                <TableRow key={rule.id} className="h-10">
                                    <TableCell>
                                        {rule.packaging_type}
                                    </TableCell>
                                    <TableCell>
                                        {rule.storage_condition}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {rule.shelf_life_days} days
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums text-muted-foreground">
                                        {rule.existing_labels_count}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {rule.last_updated_at
                                            ? new Date(
                                                  rule.last_updated_at,
                                              ).toLocaleDateString()
                                            : '—'}
                                        {rule.last_updated_by &&
                                            ` (${rule.last_updated_by})`}
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="no-print">
                                            <EditRuleDialog rule={rule} />
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}
