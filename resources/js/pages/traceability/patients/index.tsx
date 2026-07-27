import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import patients from '@/routes/patients';
import type { CursorPaginated, Patient, PatientFilters } from '@/types';

export default function PatientsIndex({
    patients: patientsPage,
    canCreate,
    filters,
}: {
    patients: CursorPaginated<Patient>;
    canCreate: boolean;
    filters: PatientFilters;
}) {
    const [q, setQ] = useState(filters.reference ?? '');
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

    function applyFilters(next: Partial<PatientFilters>) {
        const merged: Record<string, string> = {
            ...filters,
            ...next,
        } as Record<string, string>;

        for (const [key, value] of Object.entries(merged)) {
            if (!value) {
                delete merged[key];
            }
        }

        router.get(
            patients.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['patients', 'filters'],
                onError: () => setLoadError(true),
                onSuccess: () => setLoadError(false),
            },
        );
    }

    useEffect(() => {
        const handle = setTimeout(() => {
            if (q !== (filters.reference ?? '')) {
                applyFilters({ reference: q || undefined });
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    function goTo(url: string | null) {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['patients', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['patients', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Patients" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Patients
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pseudonymous references only — no real patient
                            data is ever stored here.
                        </p>
                    </div>
                    {canCreate && (
                        <div className="no-print">
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        patients.store().url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                New patient
                            </Button>
                        </div>
                    )}
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load patients — the request
                            failed. Your filters weren&apos;t lost.
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
                        placeholder="Search by reference…"
                        className="h-8 w-52"
                        aria-label="Search by reference"
                    />

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() => {
                                setQ('');
                                router.get(
                                    patients.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['patients', 'filters'],
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
                                <TableHead>Reference</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        <TableCell>
                                            <Skeleton className="h-4 w-full max-w-32" />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            {!isLoading &&
                                !loadError &&
                                patientsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell className="h-24 text-center text-muted-foreground">
                                            {hasFilters
                                                ? 'No patients match this search.'
                                                : canCreate
                                                    ? 'No patients yet — create one to start linking labels to procedures.'
                                                    : 'No patients yet.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                patientsPage.data.map((patient) => (
                                    <TableRow
                                        key={patient.id}
                                        className="h-10 cursor-pointer hover:bg-muted/40"
                                        onClick={() =>
                                            router.visit(
                                                patients.show(patient.id)
                                                    .url,
                                            )
                                        }
                                    >
                                        <TableCell>
                                            <Link
                                                href={
                                                    patients.show(patient.id)
                                                        .url
                                                }
                                                className="hover:underline"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <Mono>
                                                    {patient.reference}
                                                </Mono>
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
                        disabled={!patientsPage.prev_page_url}
                        onClick={() => goTo(patientsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!patientsPage.next_page_url}
                        onClick={() => goTo(patientsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

PatientsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Patients', href: patients.index() },
    ],
};
