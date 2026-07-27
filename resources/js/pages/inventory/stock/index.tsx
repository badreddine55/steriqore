import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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
import { dashboard } from '@/routes';
import batches from '@/routes/batches';
import products from '@/routes/products';
import stock from '@/routes/stock';
import type {
    CursorPaginated,
    Option,
    StockLevelEntry,
    StockLevelFilters,
} from '@/types';

export default function StockLevelsIndex({
    levels: levelsPage,
    products: productOptions,
    locations: locationOptions,
    filters,
}: {
    levels: CursorPaginated<StockLevelEntry>;
    products: Option[];
    locations: Option[];
    filters: StockLevelFilters;
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

    function applyFilters(next: Partial<StockLevelFilters>) {
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
            stock.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['levels', 'filters'],
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
            only: ['levels', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['levels', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Stock" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Stock
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            What&apos;s on hand, by batch and location — a
                            projection derived from the movement ledger,
                            never set directly.
                        </p>
                    </div>
                    <div className="no-print flex gap-1 rounded-md border p-1">
                        <span className="rounded px-2 py-1 text-sm font-medium bg-muted">
                            Levels
                        </span>
                        <Link
                            href={stock.movements().url}
                            className="rounded px-2 py-1 text-sm text-muted-foreground hover:bg-muted"
                        >
                            Movement ledger
                        </Link>
                    </div>
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load stock levels — the request
                            failed. Your filters weren&apos;t lost.
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

                    <label className="flex items-center gap-2 px-1 text-sm">
                        <Checkbox
                            checked={filters.in_stock_only === '1'}
                            onCheckedChange={(checked) =>
                                applyFilters({
                                    in_stock_only: checked ? '1' : undefined,
                                })
                            }
                        />
                        <Label className="font-normal">
                            In stock only
                        </Label>
                    </label>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() =>
                                router.get(
                                    stock.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['levels', 'filters'],
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
                                <TableHead>Product</TableHead>
                                <TableHead>Batch</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead className="w-28 text-right">
                                    Quantity
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
                                        {Array.from({ length: 4 }).map(
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
                                levelsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No stock levels match these filters.'
                                                : 'No stock on hand yet — receive a purchase order to see it here.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                levelsPage.data.map((level) => (
                                    <TableRow key={level.id} className="h-10">
                                        <TableCell>
                                            <Link
                                                href={
                                                    products.show(
                                                        level.product_id,
                                                    ).url
                                                }
                                                className="hover:underline"
                                            >
                                                {level.product_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={batches.index().url}
                                                className="hover:underline"
                                            >
                                                <Mono>
                                                    {level.batch_number}
                                                </Mono>
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {level.location_name}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Mono>{level.quantity}</Mono>
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
                        disabled={!levelsPage.prev_page_url}
                        onClick={() => goTo(levelsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!levelsPage.next_page_url}
                        onClick={() => goTo(levelsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

StockLevelsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Stock', href: stock.index() },
    ],
};
