import { Head, Link, router } from '@inertiajs/react';
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
import { labelStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import labels from '@/routes/labels';
import type { CursorPaginated, LabelFilters, LabelListEntry, Option } from '@/types';

const STATUS_OPTIONS = Object.keys(labelStatus);

export default function LabelsIndex({
    labels: labelsPage,
    devices,
    filters,
}: {
    labels: CursorPaginated<LabelListEntry>;
    devices: Option[];
    filters: LabelFilters;
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

    function applyFilters(next: Partial<LabelFilters>) {
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
            labels.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['labels', 'filters'],
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
            only: ['labels', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['labels', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Labels" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Labels
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Every pouch label printed across every cycle, newest
                        first.
                    </p>
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load labels — the request failed.
                            Your filters weren&apos;t lost.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="no-print flex flex-wrap items-center gap-2">
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
                                    {labelStatus[status].label}
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

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() =>
                                router.get(
                                    labels.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['labels', 'filters'],
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
                                <TableHead className="w-28">Cycle</TableHead>
                                <TableHead>Device</TableHead>
                                <TableHead>Contents</TableHead>
                                <TableHead className="w-32">Status</TableHead>
                                <TableHead className="w-32">Use by</TableHead>
                                <TableHead className="w-20">Prints</TableHead>
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
                                labelsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No labels match these filters.'
                                                : 'No labels have been generated yet — generate a sheet from a released cycle to see them here.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                labelsPage.data.map((label) => {
                                    const status =
                                        labelStatus[label.status] ??
                                        labelStatus.created;

                                    return (
                                        <TableRow
                                            key={label.id}
                                            className="h-10 cursor-pointer hover:bg-muted/40"
                                            onClick={() =>
                                                router.visit(
                                                    labels.show(label.id).url,
                                                )
                                            }
                                        >
                                            <TableCell>
                                                <Link
                                                    href={
                                                        labels.show(label.id)
                                                            .url
                                                    }
                                                    className="hover:underline"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    <Mono>
                                                        #{label.cycle_number}
                                                    </Mono>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {label.device_name}
                                            </TableCell>
                                            <TableCell>
                                                {label.description}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={status} />
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {label.use_by_date}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <Mono>
                                                    {label.print_counter}
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
                        disabled={!labelsPage.prev_page_url}
                        onClick={() => goTo(labelsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!labelsPage.next_page_url}
                        onClick={() => goTo(labelsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

LabelsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Labels', href: labels.index() },
    ],
};
