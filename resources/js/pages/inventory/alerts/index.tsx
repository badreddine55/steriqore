import { Head, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { alertSeverity, alertState, alertTypeLabel } from '@/lib/status';
import { dashboard } from '@/routes';
import alerts from '@/routes/alerts';
import type { Alert, AlertFilters, CursorPaginated } from '@/types';

const TYPE_OPTIONS = Object.keys(alertTypeLabel);
const SEVERITY_OPTIONS = Object.keys(alertSeverity);
const STATE_OPTIONS = Object.keys(alertState);

export default function AlertsIndex({
    alerts: alertsPage,
    canManage,
    filters,
}: {
    alerts: CursorPaginated<Alert>;
    canManage: boolean;
    filters: AlertFilters;
}) {
    const [isLoading, setIsLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', () => setIsLoading(true));
        const removeFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    function applyFilters(next: Partial<AlertFilters>) {
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
            alerts.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['alerts', 'filters'],
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
            only: ['alerts', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['alerts', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Alerts" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Alerts
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Low stock, near-expiry, expired batches and failed
                        control tests, tenant-wide.
                    </p>
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load alerts — the request failed.
                            Your filters weren&apos;t lost.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.type ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ type: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            {TYPE_OPTIONS.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {alertTypeLabel[type]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.severity ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ severity: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-36">
                            <SelectValue placeholder="Severity" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All severities
                            </SelectItem>
                            {SEVERITY_OPTIONS.map((severity) => (
                                <SelectItem key={severity} value={severity}>
                                    {alertSeverity[severity].label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.state ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ state: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATE_OPTIONS.map((state) => (
                                <SelectItem key={state} value={state}>
                                    {alertState[state].label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() =>
                                router.get(
                                    alerts.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['alerts', 'filters'],
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
                                <TableHead className="w-32">Type</TableHead>
                                <TableHead className="w-28">
                                    Severity
                                </TableHead>
                                <TableHead>Message</TableHead>
                                <TableHead className="w-44">
                                    Raised
                                </TableHead>
                                <TableHead className="w-32">
                                    Status
                                </TableHead>
                                {canManage && (
                                    <TableHead className="w-28" />
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({
                                            length: canManage ? 6 : 5,
                                        }).map((__, j) => (
                                            <TableCell key={j}>
                                                <Skeleton className="h-4 w-full max-w-32" />
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            {!isLoading &&
                                !loadError &&
                                alertsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={canManage ? 6 : 5}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No alerts match these filters.'
                                                : 'No alerts — everything is within range.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                alertsPage.data.map((alert) => (
                                    <TableRow key={alert.id} className="h-10">
                                        <TableCell>
                                            {alertTypeLabel[alert.type] ??
                                                alert.type}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    alertSeverity[
                                                        alert.severity
                                                    ]
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>{alert.message}</TableCell>
                                        <TableCell>
                                            <Mono className="text-xs">
                                                {formatDateTime(
                                                    alert.created_at,
                                                )}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-col gap-0.5">
                                                <StatusBadge
                                                    status={
                                                        alertState[
                                                            alert.state
                                                        ]
                                                    }
                                                />
                                                {alert.state ===
                                                    'resolved' && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {alert.resolved_by_name ??
                                                            'Auto-resolved'}
                                                        {alert.resolved_at &&
                                                            ` · ${formatDateTime(alert.resolved_at)}`}
                                                    </span>
                                                )}
                                            </div>
                                        </TableCell>
                                        {canManage && (
                                            <TableCell>
                                                {alert.state === 'open' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                alerts.resolve(
                                                                    alert.id,
                                                                ).url,
                                                                {},
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Resolve
                                                    </Button>
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="no-print flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!alertsPage.prev_page_url}
                        onClick={() => goTo(alertsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!alertsPage.next_page_url}
                        onClick={() => goTo(alertsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

AlertsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Alerts', href: alerts.index() },
    ],
};
