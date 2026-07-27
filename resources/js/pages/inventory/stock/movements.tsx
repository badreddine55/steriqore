import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, Lock, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
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
import { stockMovementTypeLabel } from '@/lib/status';
import { dashboard } from '@/routes';
import products from '@/routes/products';
import stock from '@/routes/stock';
import type {
    CursorPaginated,
    Option,
    StockMovementFilters,
    StockMovementLogEntry,
} from '@/types';

const TYPE_OPTIONS = Object.keys(stockMovementTypeLabel);

export default function StockMovementsIndex({
    movements: movementsPage,
    products: productOptions,
    locations: locationOptions,
    filters,
}: {
    movements: CursorPaginated<StockMovementLogEntry>;
    products: Option[];
    locations: Option[];
    filters: StockMovementFilters;
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

    function applyFilters(next: Partial<StockMovementFilters>) {
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
            stock.movements().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['movements', 'filters'],
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
            only: ['movements', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['movements', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Movement ledger" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Movement ledger
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every stock movement ever recorded, newest
                            first.
                        </p>
                    </div>
                    <div className="no-print flex gap-1 rounded-md border p-1">
                        <Link
                            href={stock.index().url}
                            className="rounded px-2 py-1 text-sm text-muted-foreground hover:bg-muted"
                        >
                            Levels
                        </Link>
                        <span className="rounded px-2 py-1 text-sm font-medium bg-muted">
                            Movement ledger
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    <Lock className="size-4 shrink-0" />
                    Append-only — every row here is permanent. No entry is
                    ever edited or deleted, regardless of role; a mistake
                    is corrected with a new, opposite-signed movement.
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load the ledger — the request
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
                        onValueChange={(value) =>
                            applyFilters({ type: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-36">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            {TYPE_OPTIONS.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {stockMovementTypeLabel[type]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

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
                        value={filters.location_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ location_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Location" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All locations
                            </SelectItem>
                            {locationOptions.map((location) => (
                                <SelectItem
                                    key={location.id}
                                    value={location.id}
                                >
                                    {location.label}
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
                                    stock.movements().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['movements', 'filters'],
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
                                <TableHead>Product</TableHead>
                                <TableHead>Batch</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead className="w-24 text-right">
                                    Qty
                                </TableHead>
                                <TableHead className="w-44">
                                    Occurred
                                </TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Actor</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({ length: 8 }).map(
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
                                movementsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No movements match these filters.'
                                                : 'No stock movements have been recorded yet.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                movementsPage.data.map((movement) => (
                                    <TableRow
                                        key={movement.id}
                                        className="h-10"
                                    >
                                        <TableCell>
                                            {stockMovementTypeLabel[
                                                movement.type
                                            ] ?? movement.type}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={
                                                    products.show(
                                                        movement.product_id,
                                                    ).url
                                                }
                                                className="hover:underline"
                                            >
                                                {movement.product_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Mono>
                                                {movement.batch_number}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            {movement.location_name}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Mono>
                                                {movement.qty > 0
                                                    ? `+${movement.qty}`
                                                    : movement.qty}
                                            </Mono>
                                        </TableCell>
                                        <TableCell>
                                            <Mono className="text-xs">
                                                {formatDateTime(
                                                    movement.occurred_at,
                                                )}
                                            </Mono>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {movement.reason ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {movement.actor_name ?? '—'}
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
                        disabled={!movementsPage.prev_page_url}
                        onClick={() => goTo(movementsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!movementsPage.next_page_url}
                        onClick={() => goTo(movementsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

StockMovementsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock', href: stock.index() },
        { title: 'Movement ledger', href: stock.movements() },
    ],
};
