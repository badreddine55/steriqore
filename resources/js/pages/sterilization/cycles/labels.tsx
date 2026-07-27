import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Mono } from '@/components/mono';
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
import { Label as FieldLabel } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { labelStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import cycles from '@/routes/cycles';
import cycleLabels from '@/routes/cycles/labels';
import labels from '@/routes/labels';
import type {
    Cycle,
    CycleItem,
    DluRule,
    Label as LabelType,
    Option,
} from '@/types';

function GenerateLabelsForm({
    cycle,
    dluRules,
}: {
    cycle: Cycle;
    dluRules: DluRule[];
}) {
    const form = useForm({ packaging_type: '', storage_condition: '' });

    const rule = dluRules.find(
        (r) =>
            r.packaging_type === form.data.packaging_type &&
            r.storage_condition === form.data.storage_condition,
    );

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(cycleLabels.store(cycle.id).url);
    }

    if (dluRules.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">Generate labels</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No packaging/storage rules are configured for this
                        practice yet, so a use-by date can&apos;t be
                        calculated. Ask an admin to set one up before labels
                        can be generated.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const packagingTypes = [...new Set(dluRules.map((r) => r.packaging_type))];
    // Deduplicated by storage_condition — a practice can end up with more
    // than one rule row sharing the same packaging/storage pair (e.g. a
    // correction), which would otherwise render duplicate, identical
    // options with clashing React keys.
    const storageConditionsForPackaging = [
        ...new Map(
            dluRules
                .filter((r) => r.packaging_type === form.data.packaging_type)
                .map((r) => [r.storage_condition, r]),
        ).values(),
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">Generate labels</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
                    <div>
                        <FieldLabel
                            htmlFor="packaging_type"
                            className="mb-1 block text-xs text-muted-foreground"
                        >
                            Packaging
                        </FieldLabel>
                        <Select
                            value={form.data.packaging_type}
                            onValueChange={(value) => {
                                form.setData('packaging_type', value);
                                form.setData('storage_condition', '');
                            }}
                        >
                            <SelectTrigger id="packaging_type" className="h-8 w-44">
                                <SelectValue placeholder="Select…" />
                            </SelectTrigger>
                            <SelectContent>
                                {packagingTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <FieldLabel
                            htmlFor="storage_condition"
                            className="mb-1 block text-xs text-muted-foreground"
                        >
                            Storage
                        </FieldLabel>
                        <Select
                            value={form.data.storage_condition}
                            onValueChange={(value) =>
                                form.setData('storage_condition', value)
                            }
                            disabled={!form.data.packaging_type}
                        >
                            <SelectTrigger id="storage_condition" className="h-8 w-44">
                                <SelectValue placeholder="Select…" />
                            </SelectTrigger>
                            <SelectContent>
                                {storageConditionsForPackaging.map((r) => (
                                    <SelectItem
                                        key={r.storage_condition}
                                        value={r.storage_condition}
                                    >
                                        {r.storage_condition}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={form.processing || !rule}
                    >
                        Generate labels
                    </Button>
                    {rule && (
                        <p className="text-xs text-muted-foreground">
                            Use-by = sterilization date + {rule.shelf_life_days}{' '}
                            days.
                        </p>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}

function ReprintLabelDialog({ label }: { label: LabelType }) {
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

function LabelCard({ label, item }: { label: LabelType; item?: CycleItem }) {
    const status = labelStatus[label.status] ?? labelStatus.created;

    return (
        <Card className="break-inside-avoid">
            <CardContent className="flex gap-4 py-4">
                <div className="flex flex-col items-center gap-1">
                    <img
                        src={labels.qrCode(label.id).url}
                        alt="QR code"
                        className="size-20"
                    />
                    <img
                        src={labels.datamatrix(label.id).url}
                        alt="DataMatrix code"
                        className="h-10 w-20"
                    />
                </div>
                <div className="flex-1 space-y-1 text-sm">
                    <div className="flex items-center justify-between gap-2">
                        <span className="font-medium">
                            #{item?.sequence_in_cycle ?? '—'}{' '}
                            {item?.description}
                        </span>
                        <StatusBadge status={status} />
                    </div>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-2 text-xs text-muted-foreground">
                        <dt>Sterilized</dt>
                        <dd>
                            <Mono>{formatDateTime(label.sterilized_at)}</Mono>
                        </dd>
                        <dt>Use by</dt>
                        <dd>
                            <Mono>{label.use_by_date}</Mono>
                        </dd>
                        <dt>Prints</dt>
                        <dd>
                            <Mono>{label.print_counter}</Mono>
                        </dd>
                    </dl>
                    <div className="no-print flex gap-2 pt-1">
                        <a
                            href={labels.pdf(label.id).url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Button size="sm" variant="outline">
                                Download PDF
                            </Button>
                        </a>
                        {label.print_counter === 0 ? (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        labels.print(label.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Print
                            </Button>
                        ) : (
                            <ReprintLabelDialog label={label} />
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function CycleLabels({
    cycle,
    device,
    items,
    labels: cycleLabelsList,
    dluRules,
    canGenerate,
}: {
    cycle: Cycle;
    device: Option;
    items: CycleItem[];
    labels: LabelType[];
    dluRules: DluRule[];
    canGenerate: boolean;
}) {
    const itemsById = new Map(items.map((item) => [item.id, item]));

    return (
        <>
            <Head title={`Cycle #${cycle.cycle_number} — Labels`} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="no-print">
                    <Link
                        href={cycles.show(cycle.id).url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to cycle #{cycle.cycle_number}
                    </Link>
                </div>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Labels — Cycle <Mono>#{cycle.cycle_number}</Mono>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {device.label}
                        </p>
                    </div>
                    {cycleLabelsList.length > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="no-print"
                            onClick={() => window.print()}
                        >
                            Print sheet
                        </Button>
                    )}
                </div>

                {cycleLabelsList.length === 0 && (
                    <>
                        {canGenerate ? (
                            <div className="no-print">
                                <GenerateLabelsForm
                                    cycle={cycle}
                                    dluRules={dluRules}
                                />
                            </div>
                        ) : (
                            <Card>
                                <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                    {cycle.status === 'released' ? (
                                        <>
                                            No labels have been generated for
                                            this cycle yet.
                                        </>
                                    ) : (
                                        <>
                                            Labels can only be generated once
                                            this cycle has been released as
                                            compliant.{' '}
                                            <Link
                                                href={cycles.show(cycle.id).url}
                                                className="text-primary hover:underline"
                                            >
                                                Back to cycle #
                                                {cycle.cycle_number}
                                            </Link>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}

                {cycleLabelsList.length > 0 && (
                    <div className="columns-1 gap-4 space-y-4 md:columns-2">
                        {cycleLabelsList.map((label) => (
                            <LabelCard
                                key={label.id}
                                label={label}
                                item={itemsById.get(label.cycle_item_id)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CycleLabels.layout = (props: { cycle: Cycle }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cycles', href: cycles.index() },
        {
            title: `#${props.cycle.cycle_number}`,
            href: cycles.show(props.cycle.id),
        },
        { title: 'Labels', href: cycleLabels.index(props.cycle.id) },
    ],
});
