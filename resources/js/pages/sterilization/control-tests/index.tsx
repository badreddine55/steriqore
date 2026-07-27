import { Head, Link, router } from '@inertiajs/react';
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
import { controlTestResult, controlTestTypeLabel } from '@/lib/status';
import { dashboard } from '@/routes';
import controlTests from '@/routes/control-tests';
import cycles from '@/routes/cycles';
import type { ControlTestFilters, ControlTestLogEntry, CursorPaginated, Option } from '@/types';

const TYPE_OPTIONS = Object.keys(controlTestTypeLabel);
const RESULT_OPTIONS = Object.keys(controlTestResult);

export default function ControlTestsIndex({
    tests: testsPage,
    devices,
    filters,
}: {
    tests: CursorPaginated<ControlTestLogEntry>;
    devices: Option[];
    filters: ControlTestFilters;
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

    function applyFilters(next: Partial<ControlTestFilters>) {
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
            controlTests.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['tests', 'filters'],
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
            only: ['tests', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['tests', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Control tests" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Control tests
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Every vacuum, Bowie-Dick, Helix and biological test
                        recorded, newest first.
                    </p>
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load the logbook — the request
                            failed. Your filters weren&apos;t lost.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.type ?? 'all'}
                        onValueChange={(value) => applyFilters({ type: value })}
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            {TYPE_OPTIONS.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {controlTestTypeLabel[type]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.result ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ result: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-36">
                            <SelectValue placeholder="Result" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All results</SelectItem>
                            {RESULT_OPTIONS.map((result) => (
                                <SelectItem key={result} value={result}>
                                    {controlTestResult[result].label}
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
                            aria-label="Performed from"
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
                            aria-label="Performed to"
                        />
                    </div>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() =>
                                router.get(
                                    controlTests.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['tests', 'filters'],
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
                                <TableHead>Type</TableHead>
                                <TableHead className="w-28">Result</TableHead>
                                <TableHead className="w-44">
                                    Performed
                                </TableHead>
                                <TableHead>Notes</TableHead>
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
                                testsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No control tests match these filters.'
                                                : 'No control tests have been recorded yet — record one from a completed cycle to see it here.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                testsPage.data.map((test) => (
                                    <TableRow key={test.id} className="h-10">
                                        <TableCell>
                                            <Link
                                                href={
                                                    cycles.show(test.cycle_id)
                                                        .url
                                                }
                                                className="hover:underline"
                                            >
                                                <Mono>
                                                    #{test.cycle_number}
                                                </Mono>
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {test.device_name}
                                        </TableCell>
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
                                                    ] ?? controlTestResult.fail
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
                                        <TableCell className="text-muted-foreground">
                                            {test.notes ?? '—'}
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
                        disabled={!testsPage.prev_page_url}
                        onClick={() => goTo(testsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!testsPage.next_page_url}
                        onClick={() => goTo(testsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

ControlTestsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Control tests', href: controlTests.index() },
    ],
};
