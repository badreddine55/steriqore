import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NewSupplierDialog } from '@/components/new-supplier-dialog';
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
import suppliers from '@/routes/suppliers';
import type { CursorPaginated, Supplier, SupplierFilters } from '@/types';

export default function SuppliersIndex({
    suppliers: suppliersPage,
    canCreate,
    filters,
}: {
    suppliers: CursorPaginated<Supplier>;
    canCreate: boolean;
    filters: SupplierFilters;
}) {
    const [q, setQ] = useState(filters.name ?? '');
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

    function applyFilters(next: Partial<SupplierFilters>) {
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
            suppliers.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['suppliers', 'filters'],
                onError: () => setLoadError(true),
                onSuccess: () => setLoadError(false),
            },
        );
    }

    useEffect(() => {
        const handle = setTimeout(() => {
            if (q !== (filters.name ?? '')) {
                applyFilters({ name: q || undefined });
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
            only: ['suppliers', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['suppliers', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Suppliers" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Suppliers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Vendors and their product references.
                        </p>
                    </div>
                    {canCreate && (
                        <div className="no-print">
                            <NewSupplierDialog />
                        </div>
                    )}
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load suppliers — the request
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
                        placeholder="Search by name…"
                        className="h-8 w-48"
                        aria-label="Search by name"
                    />

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() => {
                                setQ('');
                                router.get(
                                    suppliers.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['suppliers', 'filters'],
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
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Phone</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({ length: 3 }).map(
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
                                suppliersPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No suppliers match these filters.'
                                                : canCreate
                                                    ? 'No suppliers have been added yet — add one to get started.'
                                                    : 'No suppliers have been added yet.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                suppliersPage.data.map((supplier) => (
                                    <TableRow
                                        key={supplier.id}
                                        className="h-10 cursor-pointer hover:bg-muted/40"
                                        onClick={() =>
                                            router.visit(
                                                suppliers.show(supplier.id)
                                                    .url,
                                            )
                                        }
                                    >
                                        <TableCell>
                                            <Link
                                                href={
                                                    suppliers.show(
                                                        supplier.id,
                                                    ).url
                                                }
                                                className="hover:underline"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                {supplier.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {supplier.email ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {supplier.phone ?? '—'}
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
                        disabled={!suppliersPage.prev_page_url}
                        onClick={() => goTo(suppliersPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!suppliersPage.next_page_url}
                        onClick={() => goTo(suppliersPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

SuppliersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Suppliers', href: suppliers.index() },
    ],
};
