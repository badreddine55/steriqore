import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarOff,
    ChevronLeft,
    ChevronRight,
    Clock,
    X,
} from 'lucide-react';
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
import { batchStatus } from '@/lib/status';
import type { StatusMeta } from '@/lib/status';
import { dashboard } from '@/routes';
import batches from '@/routes/batches';
import products from '@/routes/products';
import suppliers from '@/routes/suppliers';
import type { Batch, BatchFilters, CursorPaginated, Option } from '@/types';

const STATUS_OPTIONS = Object.keys(batchStatus);

/**
 * A batch with a null expiry_date never carries an expiry badge — nothing
 * to warn about. The 30-day near-expiry window mirrors
 * DetectAlertsAction::NEAR_EXPIRY_DAYS exactly (same source of truth the
 * alert engine uses), not a value invented for this screen.
 */
function expiryBadge(expiryDate: string | null): StatusMeta | null {
    if (!expiryDate) {
        return null;
    }

    const days = Math.ceil(
        (new Date(expiryDate).getTime() - Date.now()) / 86_400_000,
    );

    if (days < 0) {
        return { tone: 'expired', label: 'Expired', icon: CalendarOff };
    }

    if (days <= 30) {
        return { tone: 'pending', label: 'Expiring soon', icon: Clock };
    }

    return null;
}

export default function BatchesIndex({
    batches: batchesPage,
    products: productOptions,
    suppliers: supplierOptions,
    filters,
}: {
    batches: CursorPaginated<Batch>;
    products: Option[];
    suppliers: Option[];
    filters: BatchFilters;
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

    function applyFilters(next: Partial<BatchFilters>) {
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
            batches.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['batches', 'filters'],
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
            only: ['batches', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['batches', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Batches" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Batches
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Every received batch and its expiry, across all
                        products. Batches are created by receiving a
                        purchase order, not from here.
                    </p>
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load batches — the request failed.
                            Your filters weren&apos;t lost.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.product_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ product_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Product" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All products</SelectItem>
                            {productOptions.map((product) => (
                                <SelectItem
                                    key={product.id}
                                    value={product.id}
                                >
                                    {product.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.supplier_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ supplier_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Supplier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All suppliers</SelectItem>
                            {supplierOptions.map((supplier) => (
                                <SelectItem
                                    key={supplier.id}
                                    value={supplier.id}
                                >
                                    {supplier.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ status: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-36">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUS_OPTIONS.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {batchStatus[status].label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.expiry ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ expiry: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Expiry" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Any expiry</SelectItem>
                            <SelectItem value="near_expiry">
                                Expiring soon
                            </SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() =>
                                router.get(
                                    batches.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['batches', 'filters'],
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
                                <TableHead>Batch</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead className="w-32 text-right">
                                    On hand
                                </TableHead>
                                <TableHead className="w-36">
                                    Received
                                </TableHead>
                                <TableHead className="w-40">Expiry</TableHead>
                                <TableHead className="w-32">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({ length: 7 }).map(
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
                                batchesPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No batches match these filters.'
                                                : 'No batches have been received yet — receive a purchase order to see one here.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                batchesPage.data.map((batch) => {
                                    const expiry = expiryBadge(
                                        batch.expiry_date,
                                    );

                                    return (
                                        <TableRow
                                            key={batch.id}
                                            className="h-10"
                                        >
                                            <TableCell>
                                                <Mono>
                                                    {batch.batch_number}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={
                                                        products.show(
                                                            batch.product_id,
                                                        ).url
                                                    }
                                                    className="hover:underline"
                                                >
                                                    {batch.product_name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {batch.supplier_id &&
                                                batch.supplier_name ? (
                                                    <Link
                                                        href={
                                                            suppliers.show(
                                                                batch.supplier_id,
                                                            ).url
                                                        }
                                                        className="hover:underline"
                                                    >
                                                        {batch.supplier_name}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Mono>
                                                    {batch.qty_on_hand}
                                                </Mono>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                <Mono className="text-xs">
                                                    {new Date(
                                                        batch.received_at,
                                                    ).toLocaleDateString()}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Mono className="text-xs">
                                                        {batch.expiry_date
                                                            ? new Date(
                                                                  batch.expiry_date,
                                                              ).toLocaleDateString()
                                                            : '—'}
                                                    </Mono>
                                                    {expiry && (
                                                        <StatusBadge
                                                            status={expiry}
                                                        />
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        batchStatus[
                                                            batch.status
                                                        ] ??
                                                        batchStatus.active
                                                    }
                                                />
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
                        disabled={!batchesPage.prev_page_url}
                        onClick={() => goTo(batchesPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!batchesPage.next_page_url}
                        onClick={() => goTo(batchesPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

BatchesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Batches', href: batches.index() },
    ],
};
