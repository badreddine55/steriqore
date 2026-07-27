import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Paperclip, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { CycleTimeline } from '@/components/cycle-timeline';
import { Mono } from '@/components/mono';
import { RecallDialog } from '@/components/recall-dialog';
import { StatusBadge } from '@/components/status-badge';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDateTime } from '@/lib/format';
import {
    controlTestResult,
    controlTestTypeLabel,
    cycleReleaseDecision,
    cycleStatus,
} from '@/lib/status';
import { dashboard } from '@/routes';
import cycles from '@/routes/cycles';
import cycleAttachments from '@/routes/cycles/attachments';
import cycleControlTests from '@/routes/cycles/control-tests';
import cycleItems from '@/routes/cycles/items';
import cycleLabels from '@/routes/cycles/labels';
import type {
    ControlTest,
    Cycle,
    CycleAttachment,
    CycleItem,
    CycleRelease,
    NonConformityBlastRadius,
    Option,
} from '@/types';

function CycleActions({
    cycle,
    canManage,
    itemCount,
}: {
    cycle: Cycle;
    canManage: boolean;
    itemCount: number;
}) {
    if (!canManage) {
        return null;
    }

    if (cycle.status === 'draft') {
        const button = (
            <Button
                size="sm"
                disabled={itemCount === 0}
                onClick={() =>
                    router.post(
                        cycles.start(cycle.id).url,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                Start cycle
            </Button>
        );

        if (itemCount === 0) {
            return (
                <Tooltip>
                    <TooltipTrigger asChild>
                        {/* A disabled native button doesn't fire hover events for the tooltip — the wrapper does. */}
                        <span>{button}</span>
                    </TooltipTrigger>
                    <TooltipContent>
                        Add at least one item to the load before starting.
                    </TooltipContent>
                </Tooltip>
            );
        }

        return button;
    }

    if (cycle.status === 'running') {
        return (
            <Button
                size="sm"
                onClick={() =>
                    router.post(
                        cycles.complete(cycle.id).url,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                Complete cycle — record result
            </Button>
        );
    }

    if (cycle.status === 'completed') {
        return (
            <Button
                size="sm"
                onClick={() =>
                    router.post(
                        cycles.submitForRelease(cycle.id).url,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                Submit for release
            </Button>
        );
    }

    return null;
}

function RejectCycleDialog({ cycle }: { cycle: Cycle }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ decision: 'rejected', reason: '' });

    function submit() {
        form.post(cycles.release(cycle.id).url, {
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
                <Button size="sm" variant="destructive">
                    Reject cycle
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject cycle #{cycle.cycle_number}</DialogTitle>
                    <DialogDescription>
                        This is permanent evidence — a rejection can&apos;t be
                        edited or undone afterwards, only explained.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="reject-reason">Reason</Label>
                    <Textarea
                        id="reject-reason"
                        value={form.data.reason}
                        onChange={(e) =>
                            form.setData('reason', e.target.value)
                        }
                        placeholder="What made this cycle non-compliant?"
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
                        variant="destructive"
                        onClick={submit}
                        disabled={form.processing || !form.data.reason.trim()}
                    >
                        Reject cycle
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ReleaseDecisionForm({ cycle }: { cycle: Cycle }) {
    function releaseAsCompliant() {
        router.post(
            cycles.release(cycle.id).url,
            { decision: 'compliant' },
            { preserveScroll: true },
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">Release decision</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-wrap items-center gap-2">
                <p className="mr-auto text-sm text-muted-foreground">
                    This cycle is awaiting release. Both outcomes are
                    permanent evidence.
                </p>
                <RejectCycleDialog cycle={cycle} />
                <Button size="sm" onClick={releaseAsCompliant}>
                    Release as compliant
                </Button>
            </CardContent>
        </Card>
    );
}

function AddItemForm({ cycle }: { cycle: Cycle }) {
    const form = useForm({ description: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(cycleItems.store(cycle.id).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="flex items-start gap-2">
            <div className="flex-1">
                <Input
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="e.g. Scaler kit, pouch 1"
                    aria-label="New item description"
                />
                {form.errors.description && (
                    <p className="mt-1 text-sm text-destructive">
                        {form.errors.description}
                    </p>
                )}
            </div>
            <Button
                type="submit"
                size="sm"
                disabled={form.processing || !form.data.description.trim()}
            >
                Add item
            </Button>
        </form>
    );
}

const CONTROL_TEST_TYPES = Object.keys(controlTestTypeLabel);

function defaultPerformedAt(): string {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());

    return now.toISOString().slice(0, 16);
}

function RecordControlTestForm({ cycle }: { cycle: Cycle }) {
    const form = useForm({
        type: 'vacuum',
        result: 'pass',
        performed_at: defaultPerformedAt(),
        notes: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(cycleControlTests.store(cycle.id).url, {
            preserveScroll: true,
            onSuccess: () =>
                form.setData({
                    type: 'vacuum',
                    result: 'pass',
                    performed_at: defaultPerformedAt(),
                    notes: '',
                }),
        });
    }

    return (
        <form onSubmit={submit} className="space-y-2 border-t pt-3">
            <div className="flex flex-wrap items-end gap-2">
                <div>
                    <Label
                        htmlFor="control-test-type"
                        className="mb-1 block text-xs text-muted-foreground"
                    >
                        Type
                    </Label>
                    <Select
                        value={form.data.type}
                        onValueChange={(value) => form.setData('type', value)}
                    >
                        <SelectTrigger
                            id="control-test-type"
                            size="sm"
                            className="h-8 w-36"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {CONTROL_TEST_TYPES.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {controlTestTypeLabel[type]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label
                        htmlFor="control-test-result"
                        className="mb-1 block text-xs text-muted-foreground"
                    >
                        Result
                    </Label>
                    <Select
                        value={form.data.result}
                        onValueChange={(value) =>
                            form.setData('result', value)
                        }
                    >
                        <SelectTrigger
                            id="control-test-result"
                            size="sm"
                            className="h-8 w-28"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="pass">Pass</SelectItem>
                            <SelectItem value="fail">Fail</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label
                        className="mb-1 block text-xs text-muted-foreground"
                        htmlFor="control-test-performed-at"
                    >
                        Performed at
                    </Label>
                    <Input
                        id="control-test-performed-at"
                        type="datetime-local"
                        className="h-8"
                        value={form.data.performed_at}
                        onChange={(e) =>
                            form.setData('performed_at', e.target.value)
                        }
                    />
                </div>
                <Button type="submit" size="sm" disabled={form.processing}>
                    Record test
                </Button>
            </div>
            <Textarea
                value={form.data.notes}
                onChange={(e) => form.setData('notes', e.target.value)}
                placeholder="Notes (optional)"
                aria-label="Control test notes"
                className="min-h-16"
            />
            {form.errors.performed_at && (
                <p className="text-sm text-destructive">
                    {form.errors.performed_at}
                </p>
            )}
        </form>
    );
}

function AttachmentUploadForm({ cycle }: { cycle: Cycle }) {
    const form = useForm<{ file: File | null }>({ file: null });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (!form.data.file) {
            return;
        }

        form.post(cycleAttachments.store(cycle.id).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="flex items-start gap-2">
            <div className="flex-1">
                <Input
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                    onChange={(e) =>
                        form.setData('file', e.target.files?.[0] ?? null)
                    }
                    aria-label="Attach a ticket or photo"
                />
                <p className="mt-1 text-xs text-muted-foreground">
                    JPG, PNG, WEBP or PDF, up to 10&nbsp;MB.
                </p>
                {form.errors.file && (
                    <p className="mt-1 text-sm text-destructive">
                        {form.errors.file}
                    </p>
                )}
            </div>
            <Button
                type="submit"
                size="sm"
                disabled={form.processing || !form.data.file}
            >
                Upload
            </Button>
        </form>
    );
}

export default function CycleShow({
    cycle,
    device,
    program,
    operator,
    items,
    controlTests,
    attachments,
    release,
    releasedBy,
    nonConformityBlastRadius,
}: {
    cycle: Cycle;
    device: Option;
    program: Option | null;
    operator: Option;
    items: CycleItem[];
    controlTests: ControlTest[];
    attachments: CycleAttachment[];
    release: CycleRelease | null;
    releasedBy: Option | null;
    nonConformityBlastRadius: NonConformityBlastRadius;
}) {
    const status = cycleStatus[cycle.status] ?? cycleStatus.draft;
    const { auth } = usePage().props;
    const canManage = auth.permissions.includes('cycles.manage');
    const canRelease = auth.permissions.includes('cycles.release');
    const canRaiseNonConformity = auth.permissions.includes(
        'non_conformities.manage',
    );
    const loadLocked = cycle.status !== 'draft';

    return (
        <>
            <Head title={`Cycle #${cycle.cycle_number}`} />

            <div className="flex h-full flex-col gap-6 p-4">
                <div>
                    <Link
                        href={cycles.index().url}
                        className="no-print mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to cycles
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <h1 className="text-lg font-semibold tracking-tight">
                                Cycle <Mono>#{cycle.cycle_number}</Mono>
                            </h1>
                            <StatusBadge status={status} />
                        </div>
                        <div className="no-print flex items-center gap-2">
                            {cycle.status === 'released' && (
                                <Link href={cycleLabels.index(cycle.id).url}>
                                    <Button size="sm" variant="outline">
                                        View labels
                                    </Button>
                                </Link>
                            )}
                            <CycleActions
                                cycle={cycle}
                                canManage={canManage}
                                itemCount={items.length}
                            />
                            {canRaiseNonConformity && (
                                <RecallDialog
                                    subjectType="cycle"
                                    subjectId={cycle.id}
                                    subjectLabel={`cycle #${cycle.cycle_number}`}
                                    blastRadius={nonConformityBlastRadius}
                                />
                            )}
                        </div>
                    </div>
                    <dl className="mt-1 flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground">
                        <div className="flex gap-1">
                            <dt className="font-medium">Device:</dt>
                            <dd>{device.label}</dd>
                        </div>
                        {program && (
                            <div className="flex gap-1">
                                <dt className="font-medium">Program:</dt>
                                <dd>{program.label}</dd>
                            </div>
                        )}
                        <div className="flex gap-1">
                            <dt className="font-medium">Operator:</dt>
                            <dd>{operator.label}</dd>
                        </div>
                        <div className="flex gap-1">
                            <dt className="font-medium">Started:</dt>
                            <dd>
                                <Mono>{formatDateTime(cycle.started_at)}</Mono>
                            </dd>
                        </div>
                        <div className="flex gap-1">
                            <dt className="font-medium">Completed:</dt>
                            <dd>
                                <Mono>
                                    {formatDateTime(cycle.completed_at)}
                                </Mono>
                            </dd>
                        </div>
                    </dl>
                </div>

                <Card>
                    <CardContent className="overflow-x-auto py-2">
                        <CycleTimeline status={cycle.status} />
                    </CardContent>
                </Card>

                {release && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Release decision
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                            <StatusBadge
                                status={
                                    cycleReleaseDecision[release.decision] ??
                                    cycleReleaseDecision.rejected
                                }
                            />
                            <span className="text-muted-foreground">
                                by{' '}
                                <span className="text-foreground">
                                    {releasedBy?.label ?? '—'}
                                </span>
                            </span>
                            <span className="text-muted-foreground">
                                <Mono>
                                    {formatDateTime(release.released_at)}
                                </Mono>
                            </span>
                            {release.reason && (
                                <span className="text-muted-foreground italic">
                                    &ldquo;{release.reason}&rdquo;
                                </span>
                            )}
                        </CardContent>
                    </Card>
                )}

                {!release &&
                    cycle.status === 'awaiting_release' &&
                    canRelease && <ReleaseDecisionForm cycle={cycle} />}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-sm">
                                Load composition
                            </CardTitle>
                            {loadLocked && (
                                <span className="text-xs text-muted-foreground">
                                    Locked once the cycle is running.
                                </span>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {items.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No items have been added to this load yet.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12">
                                                #
                                            </TableHead>
                                            <TableHead>Description</TableHead>
                                            <TableHead>Batch</TableHead>
                                            {!loadLocked && canManage && (
                                                <TableHead className="w-10" />
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <Mono>
                                                        {item.sequence_in_cycle}
                                                    </Mono>
                                                </TableCell>
                                                <TableCell>
                                                    {item.description}
                                                </TableCell>
                                                <TableCell>
                                                    {item.batch_id ? (
                                                        <Mono className="text-xs">
                                                            {item.batch_id}
                                                        </Mono>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                {!loadLocked && canManage && (
                                                    <TableCell>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Remove item ${item.sequence_in_cycle}`}
                                                            onClick={() =>
                                                                router.delete(
                                                                    cycleItems.destroy(
                                                                        {
                                                                            cycle: cycle.id,
                                                                            item: item.id,
                                                                        },
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4 text-muted-foreground" />
                                                        </Button>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                            {!loadLocked && canManage && (
                                <AddItemForm cycle={cycle} />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Control tests
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {controlTests.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No control tests recorded for this cycle
                                    yet.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Result</TableHead>
                                            <TableHead>Performed</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {controlTests.map((test) => (
                                            <TableRow key={test.id}>
                                                <TableCell>
                                                    {controlTestTypeLabel[
                                                        test.type
                                                    ] ?? test.type}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        status={
                                                            controlTestResult[
                                                                test.result
                                                            ] ??
                                                            controlTestResult.fail
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Mono className="text-xs">
                                                        {formatDateTime(
                                                            test.performed_at,
                                                        )}
                                                    </Mono>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                            {canManage &&
                                (cycle.status === 'completed' ||
                                    cycle.status === 'awaiting_release') && (
                                    <RecordControlTestForm cycle={cycle} />
                                )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Attachments</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {attachments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No tickets or photos have been attached to this
                                cycle yet.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {attachments.map((attachment) => (
                                    <li
                                        key={attachment.id}
                                        className="flex items-center justify-between gap-4 py-2 text-sm"
                                    >
                                        <span className="flex items-center gap-2">
                                            <Paperclip className="size-4 text-muted-foreground" />
                                            <a
                                                href={attachment.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-primary hover:underline"
                                            >
                                                {attachment.file_name}
                                            </a>
                                        </span>
                                        <span className="flex items-center gap-3">
                                            <span className="text-muted-foreground">
                                                <Mono className="text-xs">
                                                    {formatDateTime(
                                                        attachment.created_at,
                                                    )}
                                                </Mono>
                                            </span>
                                            {canManage && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="no-print"
                                                    aria-label={`Remove ${attachment.file_name}`}
                                                    onClick={() =>
                                                        router.delete(
                                                            cycleAttachments.destroy(
                                                                {
                                                                    cycle: cycle.id,
                                                                    media: attachment.id,
                                                                },
                                                            ).url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4 text-muted-foreground" />
                                                </Button>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {canManage && <AttachmentUploadForm cycle={cycle} />}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CycleShow.layout = (props: { cycle: Cycle }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cycles', href: cycles.index() },
        {
            title: `#${props.cycle.cycle_number}`,
            href: cycles.show(props.cycle.id),
        },
    ],
});
