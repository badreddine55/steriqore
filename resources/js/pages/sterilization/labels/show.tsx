import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Mono } from '@/components/mono';
import { ReprintLabelDialog } from '@/components/reprint-label-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label as FormLabel } from '@/components/ui/label';
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
import { formatDateTime } from '@/lib/format';
import { labelStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import cycles from '@/routes/cycles';
import labels from '@/routes/labels';
import labelUsage from '@/routes/labels/usage';
import patients from '@/routes/patients';
import type {
    Cycle,
    CycleItem,
    Label as LabelType,
    LabelPrint,
    LabelUsage,
    Option,
} from '@/types';

const UNUSABLE_STATUSES = ['recalled', 'voided', 'expired'];

function RecordUsageForm({
    label,
    patients: patientOptions,
    practitioners,
}: {
    label: LabelType;
    patients: Option[];
    practitioners: Option[];
}) {
    const form = useForm({
        patient_id: '',
        practitioner_id: '',
        procedure: '',
        notes: '',
    });

    function submit() {
        form.post(labelUsage.store(label.id).url, {
            preserveScroll: true,
        });
    }

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <FormLabel htmlFor="usage-patient">Patient</FormLabel>
                <Select
                    value={form.data.patient_id}
                    onValueChange={(value) =>
                        form.setData('patient_id', value)
                    }
                >
                    <SelectTrigger id="usage-patient" className="w-full">
                        <SelectValue placeholder="Select a patient" />
                    </SelectTrigger>
                    <SelectContent>
                        {patientOptions.map((p) => (
                            <SelectItem key={p.id} value={p.id}>
                                {p.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {form.errors.patient_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.patient_id}
                    </p>
                )}
                <p className="text-xs text-muted-foreground">
                    Don&apos;t see the right patient?{' '}
                    <Link
                        href={patients.index().url}
                        className="underline"
                    >
                        Create one
                    </Link>
                    , then come back here.
                </p>
            </div>

            <div className="grid gap-2">
                <FormLabel htmlFor="usage-practitioner">
                    Practitioner
                </FormLabel>
                <Select
                    value={form.data.practitioner_id}
                    onValueChange={(value) =>
                        form.setData('practitioner_id', value)
                    }
                >
                    <SelectTrigger
                        id="usage-practitioner"
                        className="w-full"
                    >
                        <SelectValue placeholder="Select a practitioner" />
                    </SelectTrigger>
                    <SelectContent>
                        {practitioners.map((p) => (
                            <SelectItem key={p.id} value={p.id}>
                                {p.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {form.errors.practitioner_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.practitioner_id}
                    </p>
                )}
            </div>

            <div className="grid gap-2">
                <FormLabel htmlFor="usage-procedure">Procedure</FormLabel>
                <Input
                    id="usage-procedure"
                    value={form.data.procedure}
                    onChange={(e) =>
                        form.setData('procedure', e.target.value)
                    }
                    placeholder="e.g. Détartrage"
                />
                {form.errors.procedure && (
                    <p className="text-sm text-destructive">
                        {form.errors.procedure}
                    </p>
                )}
            </div>

            <div className="grid gap-2">
                <FormLabel htmlFor="usage-notes">
                    Notes (optional)
                </FormLabel>
                <Textarea
                    id="usage-notes"
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />
            </div>

            <div>
                <Button
                    onClick={submit}
                    disabled={
                        form.processing ||
                        !form.data.patient_id ||
                        !form.data.practitioner_id ||
                        !form.data.procedure.trim()
                    }
                >
                    Link label to procedure
                </Button>
            </div>
        </div>
    );
}

export default function LabelShow({
    label,
    cycle,
    device,
    site,
    item,
    prints,
    printedByUsers,
    usage,
    patient,
    practitioner,
    patients: patientOptions,
    practitioners,
    canRecordUsage,
}: {
    label: LabelType;
    cycle: Cycle;
    device: Option;
    site: Option;
    item: CycleItem;
    prints: LabelPrint[];
    printedByUsers: Option[];
    usage: LabelUsage | null;
    patient: Option | null;
    practitioner: Option | null;
    patients: Option[];
    practitioners: Option[];
    canRecordUsage: boolean;
}) {
    const status = labelStatus[label.status] ?? labelStatus.created;
    const printedByName = (userId: string) =>
        printedByUsers.find((u) => u.id === userId)?.label ?? '—';

    return (
        <>
            <Head title={`Label — ${item.description}`} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="no-print">
                    <Link
                        href={labels.index().url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to labels
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-lg font-semibold tracking-tight">
                                {item.description}
                            </h1>
                            <StatusBadge status={status} />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            <Mono>{label.urn}</Mono>
                        </p>
                    </div>
                    <div className="no-print flex gap-2">
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

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Chain</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                <dt className="text-muted-foreground">
                                    Practice/site
                                </dt>
                                <dd>{site.label}</dd>
                                <dt className="text-muted-foreground">
                                    Device
                                </dt>
                                <dd>{device.label}</dd>
                                <dt className="text-muted-foreground">
                                    Cycle
                                </dt>
                                <dd>
                                    <Link
                                        href={cycles.show(cycle.id).url}
                                        className="hover:underline"
                                    >
                                        <Mono>#{cycle.cycle_number}</Mono>
                                    </Link>
                                </dd>
                                <dt className="text-muted-foreground">
                                    Sequence
                                </dt>
                                <dd>
                                    <Mono>
                                        {item.sequence_in_cycle}
                                    </Mono>
                                </dd>
                                <dt className="text-muted-foreground">
                                    Batch
                                </dt>
                                <dd>
                                    {item.batch_id ? (
                                        <Mono className="text-xs">
                                            {item.batch_id}
                                        </Mono>
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Sterilization &amp; DLU
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex gap-4">
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
                            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                <dt className="text-muted-foreground">
                                    Sterilized
                                </dt>
                                <dd>
                                    <Mono>
                                        {formatDateTime(label.sterilized_at)}
                                    </Mono>
                                </dd>
                                <dt className="text-muted-foreground">
                                    Use by
                                </dt>
                                <dd>
                                    <Mono>{label.use_by_date}</Mono>
                                </dd>
                                <dt className="text-muted-foreground">
                                    Packaging
                                </dt>
                                <dd>{label.packaging_type}</dd>
                                <dt className="text-muted-foreground">
                                    Storage
                                </dt>
                                <dd>{label.storage_condition}</dd>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">
                            Print history
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {prints.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                This label hasn&apos;t been printed yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-16">
                                            #
                                        </TableHead>
                                        <TableHead>Printed by</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>Printed at</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {prints.map((print) => (
                                        <TableRow key={print.id}>
                                            <TableCell>
                                                <Mono>{print.sequence}</Mono>
                                            </TableCell>
                                            <TableCell>
                                                {printedByName(
                                                    print.printed_by_user_id,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {print.reason ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {formatDateTime(
                                                        print.printed_at,
                                                    )}
                                                </Mono>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">
                            Point-of-care usage
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {usage ? (
                            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                <dt className="text-muted-foreground">
                                    Patient
                                </dt>
                                <dd>
                                    <Mono>
                                        {patient?.label ?? '—'}
                                    </Mono>
                                </dd>
                                <dt className="text-muted-foreground">
                                    Practitioner
                                </dt>
                                <dd>{practitioner?.label ?? '—'}</dd>
                                <dt className="text-muted-foreground">
                                    Procedure
                                </dt>
                                <dd>{usage.procedure}</dd>
                                <dt className="text-muted-foreground">
                                    Used at
                                </dt>
                                <dd>
                                    <Mono>
                                        {formatDateTime(usage.used_at)}
                                    </Mono>
                                </dd>
                                {usage.notes && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            Notes
                                        </dt>
                                        <dd>{usage.notes}</dd>
                                    </>
                                )}
                            </dl>
                        ) : UNUSABLE_STATUSES.includes(label.status) ? (
                            <p className="text-sm text-muted-foreground">
                                This label is {label.status} and can no
                                longer be linked to a procedure.
                            </p>
                        ) : canRecordUsage ? (
                            <RecordUsageForm
                                label={label}
                                patients={patientOptions}
                                practitioners={practitioners}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Not yet scanned at the point of care — this
                                pouch hasn&apos;t been used on a patient yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LabelShow.layout = (props: { label: LabelType }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Labels', href: labels.index() },
        { title: props.label.urn.slice(-8), href: labels.show(props.label.id) },
    ],
});
