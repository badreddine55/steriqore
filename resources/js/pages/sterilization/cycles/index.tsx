import { Head, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { cycleStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import cycles from '@/routes/cycles';
import type { CursorPaginated, Cycle, CycleFilters, Option } from '@/types';

const STATUS_OPTIONS = Object.keys(cycleStatus);

export default function CyclesIndex({
    cycles: cyclesPage,
    devices,
    operators,
    filters,
}: {
    cycles: CursorPaginated<Cycle>;
    devices: Option[];
    operators: Option[];
    filters: CycleFilters;
}) {
    const [q, setQ] = useState(filters.q ?? '');
    const [isLoading, setIsLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);

    // Inertia's global top progress bar covers full page navigation, but a
    // filtered/paginated reload of just this table benefits from a more
    // localized signal — the rows themselves go quiet rather than the whole
    // page appearing to hang.
    useEffect(() => {
        const removeStart = router.on('start', () => setIsLoading(true));
        const removeFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    // Debounced free-text search — every other filter below applies
    // immediately on change, since a select/date change is already a
    // single deliberate action.
    useEffect(() => {
        const handle = setTimeout(() => {
            if (q !== (filters.q ?? '')) {
                applyFilters({ q: q || undefined });
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    function applyFilters(next: Partial<CycleFilters>) {
        const merged: Record<string, string> = {
            ...filters,
            ...next,
        } as Record<string, string>;

        for (const [key, value] of Object.entries(merged)) {
            if (!value || value === 'all') {
                delete merged[key];
            }
        }

        // spatie/laravel-query-builder reads filters from a nested
        // `filter[...]` query bucket, not bare top-level params — the same
        // convention every existing endpoint in this app already uses.
        router.get(
            cycles.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['cycles', 'filters'],
                onError: () => setLoadError(true),
                onSuccess: () => setLoadError(false),
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
            only: ['cycles', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['cycles', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Cycles" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Cycles
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every sterilization run, newest first.
                        </p>
                    </div>
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load cycles — the request failed. Your
                            filters weren&apos;t lost.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Cycle number…"
                        className="h-8 w-40"
                        aria-label="Search by cycle number"
                    />

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ status: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUS_OPTIONS.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {cycleStatus[status].label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.device_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ device_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Device" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All devices</SelectItem>
                            {devices.map((device) => (
                                <SelectItem key={device.id} value={device.id}>
                                    {device.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.operator_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ operator_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Operator" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All operators</SelectItem>
                            {operators.map((operator) => (
                                <SelectItem
                                    key={operator.id}
                                    value={operator.id}
                                >
                                    {operator.label}
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
                            aria-label="Started from"
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
                            aria-label="Started to"
                        />
                    </div>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() => {
                                setQ('');
                                router.get(
                                    cycles.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['cycles', 'filters'],
                                    },
                                );
                            }}
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
                                <TableHead className="w-28">Cycle</TableHead>
                                <TableHead>Device</TableHead>
                                <TableHead>Operator</TableHead>
                                <TableHead className="w-40">Status</TableHead>
                                <TableHead className="w-44">Started</TableHead>
                                <TableHead className="w-44">
                                    Completed
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({ length: 6 }).map(
                                            (__, j) => (
                                                <TableCell key={j}>
                                                    <Skeleton className="h-4 w-full max-w-32" />
                                                </TableCell>
                                            ),
                                        )}
                                    </TableRow>
                                ))}
                            {!isLoading &&
                                !loadError &&
                                cyclesPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No cycles match these filters.'
                                                : 'No cycles have been run yet — start one from an autoclave to see it here.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                cyclesPage.data.map((cycle) => {
                                    const device = devices.find(
                                        (d) => d.id === cycle.device_id,
                                    );
                                    const operator = operators.find(
                                        (o) => o.id === cycle.operator_id,
                                    );
                                    const status =
                                        cycleStatus[cycle.status] ??
                                        cycleStatus.draft;

                                    return (
                                        <TableRow
                                            key={cycle.id}
                                            className="h-10"
                                        >
                                            <TableCell>
                                                <Mono>
                                                    #{cycle.cycle_number}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                {device?.label ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {operator?.label ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={status} />
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {formatDateTime(
                                                        cycle.started_at,
                                                    )}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {formatDateTime(
                                                        cycle.completed_at,
                                                    )}
                                                </Mono>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                        </TableBody>
                    </Table>
                </div>

                <div className="no-print flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!cyclesPage.prev_page_url}
                        onClick={() => goTo(cyclesPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!cyclesPage.next_page_url}
                        onClick={() => goTo(cyclesPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

CyclesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cycles', href: cycles.index() },
    ],
};
