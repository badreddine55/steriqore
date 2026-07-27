import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Paperclip } from 'lucide-react';
import { CycleTimeline } from '@/components/cycle-timeline';
import { Mono } from '@/components/mono';
import { StatusBadge } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import {
    controlTestResult,
    controlTestTypeLabel,
    cycleReleaseDecision,
    cycleStatus,
} from '@/lib/status';
import { dashboard } from '@/routes';
import cycles from '@/routes/cycles';
import type {
    ControlTest,
    Cycle,
    CycleAttachment,
    CycleItem,
    CycleRelease,
    Option,
} from '@/types';

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
}) {
    const status = cycleStatus[cycle.status] ?? cycleStatus.draft;

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
                    <div className="flex items-center gap-3">
                        <h1 className="text-lg font-semibold tracking-tight">
                            Cycle <Mono>#{cycle.cycle_number}</Mono>
                        </h1>
                        <StatusBadge status={status} />
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

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Load composition
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
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
                                Control tests
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
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
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Attachments</CardTitle>
                    </CardHeader>
                    <CardContent>
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
                                        <span className="text-muted-foreground">
                                            <Mono className="text-xs">
                                                {formatDateTime(
                                                    attachment.created_at,
                                                )}
                                            </Mono>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
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
