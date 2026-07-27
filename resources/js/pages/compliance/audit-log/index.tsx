import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import auditLog from '@/routes/audit-log';
import type {
    AuditEvent,
    AuditEventFilters,
    CursorPaginated,
    Option,
} from '@/types';

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

/** Every key present in either side, old -> new, changed ones highlighted. */
function ValueDiff({ event }: { event: AuditEvent }) {
    const { old_values: oldValues, new_values: newValues } = event;

    if (!oldValues && !newValues) {
        return (
            <p className="text-sm text-muted-foreground">
                No field values were recorded for this event.
            </p>
        );
    }

    const keys = Array.from(
        new Set([
            ...Object.keys(oldValues ?? {}),
            ...Object.keys(newValues ?? {}),
        ]),
    );

    return (
        <div className="overflow-hidden rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Field</TableHead>
                        {oldValues && <TableHead>Before</TableHead>}
                        <TableHead>{oldValues ? 'After' : 'Recorded'}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {keys.map((key) => {
                        const oldVal = oldValues?.[key];
                        const newVal = newValues?.[key];
                        const changed =
                            oldValues &&
                            newValues &&
                            JSON.stringify(oldVal) !== JSON.stringify(newVal);

                        return (
                            <TableRow key={key}>
                                <TableCell className="font-medium">
                                    {key}
                                </TableCell>
                                {oldValues && (
                                    <TableCell
                                        className={
                                            changed
                                                ? 'text-status-danger-fg line-through'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        <Mono className="text-xs">
                                            {formatValue(oldVal)}
                                        </Mono>
                                    </TableCell>
                                )}
                                <TableCell
                                    className={
                                        changed
                                            ? 'font-medium text-status-success-fg'
                                            : undefined
                                    }
                                >
                                    <Mono className="text-xs">
                                        {formatValue(newVal ?? oldVal)}
                                    </Mono>
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

function AuditEventDetailsDialog({ event }: { event: AuditEvent }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
                Details
            </Button>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{event.action}</DialogTitle>
                </DialogHeader>

                <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">Actor</dt>
                    <dd>{event.actor_label_snapshot ?? '—'}</dd>
                    <dt className="text-muted-foreground">Subject</dt>
                    <dd>
                        <Mono className="text-xs">
                            {event.subject_type.split('\\').pop()} ·{' '}
                            {event.subject_id}
                        </Mono>
                    </dd>
                    <dt className="text-muted-foreground">Occurred</dt>
                    <dd>
                        <Mono>{formatDateTime(event.occurred_at)}</Mono>
                    </dd>
                    {event.reason && (
                        <>
                            <dt className="text-muted-foreground">Reason</dt>
                            <dd>{event.reason}</dd>
                        </>
                    )}
                </dl>

                <ValueDiff event={event} />
            </DialogContent>
        </Dialog>
    );
}

export default function AuditLogIndex({
    events: eventsPage,
    actors,
    actions,
    subjectTypes,
    filters,
}: {
    events: CursorPaginated<AuditEvent>;
    actors: Option[];
    actions: string[];
    subjectTypes: Option[];
    filters: AuditEventFilters;
}) {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', () => setIsLoading(true));
        const removeFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    function applyFilters(next: Partial<AuditEventFilters>) {
        const merged: Record<string, string> = {
            ...filters,
            ...next,
        } as Record<string, string>;

        for (const [key, value] of Object.entries(merged)) {
            if (!value || value === 'all') {
                delete merged[key];
            }
        }

        router.get(
            auditLog.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['events', 'filters'],
            },
        );
    }

    function goTo(url: string | null) {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['events', 'filters'],
        });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Audit log" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Audit log
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Every sensitive action, append-only — who, what,
                        and the exact field values before and after.
                    </p>
                </div>

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.actor_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ actor_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Actor" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All actors</SelectItem>
                            {actors.map((actor) => (
                                <SelectItem key={actor.id} value={actor.id}>
                                    {actor.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.action ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ action: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Action" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All actions</SelectItem>
                            {actions.map((action) => (
                                <SelectItem key={action} value={action}>
                                    {action}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.subject_type ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ subject_type: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Subject" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All subjects
                            </SelectItem>
                            {subjectTypes.map((type) => (
                                <SelectItem key={type.id} value={type.id}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <div className="flex items-center gap-1">
                        <Input
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(e) =>
                                applyFilters({
                                    from: e.target.value || undefined,
                                })
                            }
                            className="h-8 w-36"
                            aria-label="Occurred from"
                        />
                        <span className="text-xs text-muted-foreground">
                            to
                        </span>
                        <Input
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(e) =>
                                applyFilters({
                                    to: e.target.value || undefined,
                                })
                            }
                            className="h-8 w-36"
                            aria-label="Occurred to"
                        />
                    </div>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() =>
                                router.get(
                                    auditLog.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['events', 'filters'],
                                    },
                                )
                            }
                        >
                            <X className="size-3.5" />
                            Clear filters
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-muted/40">
                            <TableRow>
                                <TableHead className="w-44">
                                    Occurred
                                </TableHead>
                                <TableHead>Actor</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Subject</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({ length: 5 }).map(
                                            (__, j) => (
                                                <TableCell key={j}>
                                                    <Skeleton className="h-4 w-full max-w-32" />
                                                </TableCell>
                                            ),
                                        )}
                                    </TableRow>
                                ))}
                            {!isLoading && eventsPage.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {hasFilters
                                            ? 'No audit events match these filters.'
                                            : 'No audit events recorded yet.'}
                                    </TableCell>
                                </TableRow>
                            )}
                            {!isLoading &&
                                eventsPage.data.map((event) => (
                                    <TableRow key={event.id} className="h-10">
                                        <TableCell>
                                            <Mono className="text-xs">
                                                {formatDateTime(
                                                    event.occurred_at,
                                                )}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            {event.actor_label_snapshot ??
                                                '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Mono className="text-xs">
                                                {event.action}
                                            </Mono>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {event.subject_type
                                                .split('\\')
                                                .pop()}
                                        </TableCell>
                                        <TableCell>
                                            <AuditEventDetailsDialog
                                                event={event}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="no-print flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!eventsPage.prev_page_url}
                        onClick={() => goTo(eventsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!eventsPage.next_page_url}
                        onClick={() => goTo(eventsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

AuditLogIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Audit log', href: auditLog.index() },
    ],
};
