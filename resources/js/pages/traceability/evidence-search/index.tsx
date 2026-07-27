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
import { labelStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import evidenceSearch from '@/routes/evidence-search';
import labels from '@/routes/labels';
import type {
    CursorPaginated,
    EvidenceSearchFilters,
    EvidenceSearchResult,
    Option,
} from '@/types';

export default function EvidenceSearchIndex({
    results: resultsPage,
    devices,
    operators,
    filters,
    canExport,
}: {
    results: CursorPaginated<EvidenceSearchResult>;
    devices: Option[];
    operators: Option[];
    filters: EvidenceSearchFilters;
    canExport: boolean;
}) {
    const [patientReference, setPatientReference] = useState(
        filters.patient_reference ?? '',
    );
    const [cycleNumber, setCycleNumber] = useState(
        filters.cycle_number?.toString() ?? '',
    );
    const [batchNumber, setBatchNumber] = useState(filters.batch_number ?? '');
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

    function applyFilters(
        next: Record<string, string | number | undefined>,
    ) {
        const merged: Record<string, string> = {
            ...filters,
            ...next,
        } as Record<string, string>;

        for (const [key, value] of Object.entries(merged)) {
            if (!value || value === 'all') {
                delete merged[key];
            }
        }

        router.get(evidenceSearch.index().url, merged, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['results', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    useEffect(() => {
        const handle = setTimeout(() => {
            if (patientReference !== (filters.patient_reference ?? '')) {
                applyFilters({
                    patient_reference: patientReference || undefined,
                });
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [patientReference]);

    useEffect(() => {
        const handle = setTimeout(() => {
            if (cycleNumber !== (filters.cycle_number?.toString() ?? '')) {
                applyFilters({
                    cycle_number: cycleNumber
                        ? Number(cycleNumber)
                        : undefined,
                });
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cycleNumber]);

    useEffect(() => {
        const handle = setTimeout(() => {
            if (batchNumber !== (filters.batch_number ?? '')) {
                applyFilters({ batch_number: batchNumber || undefined });
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [batchNumber]);

    function retry() {
        setLoadError(false);
        router.reload({ only: ['results', 'filters'] });
    }

    function goTo(url: string | null) {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['results', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function clearAll() {
        setPatientReference('');
        setCycleNumber('');
        setBatchNumber('');
        router.get(
            evidenceSearch.index().url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['results', 'filters'],
            },
        );
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Evidence search" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Evidence search
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every pouch traced end to end — patient, cycle,
                            batch, device, operator and period.
                        </p>
                    </div>
                    {canExport && (
                        <div className="no-print flex gap-2">
                            <a
                                href={
                                    evidenceSearch.export({
                                        query: { ...filters, format: 'csv' },
                                    }).url
                                }
                            >
                                <Button size="sm" variant="outline">
                                    Export CSV
                                </Button>
                            </a>
                            <a
                                href={
                                    evidenceSearch.export({
                                        query: { ...filters, format: 'xlsx' },
                                    }).url
                                }
                            >
                                <Button size="sm" variant="outline">
                                    Export XLSX
                                </Button>
                            </a>
                        </div>
                    )}
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t search — the request failed. Your
                            filters weren&apos;t lost.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Input
                        value={patientReference}
                        onChange={(e) =>
                            setPatientReference(e.target.value)
                        }
                        placeholder="Patient reference…"
                        className="h-8 w-44"
                        aria-label="Patient reference"
                    />
                    <Input
                        value={cycleNumber}
                        onChange={(e) => setCycleNumber(e.target.value)}
                        placeholder="Cycle #"
                        type="number"
                        className="h-8 w-28"
                        aria-label="Cycle number"
                    />
                    <Input
                        value={batchNumber}
                        onChange={(e) => setBatchNumber(e.target.value)}
                        placeholder="Batch number…"
                        className="h-8 w-40"
                        aria-label="Batch number"
                    />

                    <Select
                        value={filters.device_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ device_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
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
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Operator" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All operators
                            </SelectItem>
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
                            aria-label="Used from"
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
                            aria-label="Used to"
                        />
                    </div>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={clearAll}
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
                                <TableHead>Batch</TableHead>
                                <TableHead>Patient</TableHead>
                                <TableHead>Practitioner</TableHead>
                                <TableHead>Procedure</TableHead>
                                <TableHead className="w-44">Used</TableHead>
                                <TableHead className="w-28">Label</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({ length: 9 }).map(
                                            (__, j) => (
                                                <TableCell key={j}>
                                                    <Skeleton className="h-4 w-full max-w-24" />
                                                </TableCell>
                                            ),
                                        )}
                                    </TableRow>
                                ))}
                            {!isLoading &&
                                !loadError &&
                                resultsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={9}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No evidence matches these filters.'
                                                : 'No pouch has been used on a patient yet.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                resultsPage.data.map((result) => (
                                    <TableRow
                                        key={result.label_id}
                                        className="h-10"
                                    >
                                        <TableCell>
                                            <Mono>
                                                #{result.cycle_number}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            {result.device_name}
                                        </TableCell>
                                        <TableCell>
                                            {result.operator_name}
                                        </TableCell>
                                        <TableCell>
                                            <Mono>
                                                {result.batch_number ?? '—'}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            <Mono>
                                                {result.patient_reference}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            {result.practitioner_name}
                                        </TableCell>
                                        <TableCell>
                                            {result.procedure}
                                        </TableCell>
                                        <TableCell>
                                            <Mono className="text-xs">
                                                {formatDateTime(
                                                    result.used_at,
                                                )}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={
                                                    labels.show(
                                                        result.label_id,
                                                    ).url
                                                }
                                                className="hover:underline"
                                            >
                                                <StatusBadge
                                                    status={
                                                        labelStatus[
                                                            result
                                                                .label_status
                                                        ] ??
                                                        labelStatus.used
                                                    }
                                                />
                                            </Link>
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
                        disabled={!resultsPage.prev_page_url}
                        onClick={() => goTo(resultsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!resultsPage.next_page_url}
                        onClick={() => goTo(resultsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

EvidenceSearchIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Evidence search', href: evidenceSearch.index() },
    ],
};
