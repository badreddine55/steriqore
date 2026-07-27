import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NewCategoryDialog } from '@/components/new-category-dialog';
import { NewProductDialog } from '@/components/new-product-dialog';
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
import { dashboard } from '@/routes';
import products from '@/routes/products';
import type {
    CursorPaginated,
    Product,
    ProductCategory,
    ProductFilters,
} from '@/types';

export default function ProductsIndex({
    products: productsPage,
    categories,
    canCreate,
    filters,
}: {
    products: CursorPaginated<Product>;
    categories: ProductCategory[];
    canCreate: boolean;
    filters: ProductFilters;
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

    useEffect(() => {
        const handle = setTimeout(() => {
            if (q !== (filters.name ?? '')) {
                applyFilters({ name: q || undefined });
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    function applyFilters(next: Partial<ProductFilters>) {
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
            products.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['products', 'filters'],
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
            only: ['products', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['products', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Products" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Products
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Everything stocked and tracked in inventory.
                        </p>
                    </div>
                    {canCreate && (
                        <div className="no-print flex gap-2">
                            <NewCategoryDialog categories={categories} />
                            <NewProductDialog categories={categories} />
                        </div>
                    )}
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load products — the request failed.
                            Your filters weren&apos;t lost.
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

                    <Select
                        value={filters.category_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ category_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
                            {categories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={category.id}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.is_sterilizable ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ is_sterilizable: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Sterilizable" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All products</SelectItem>
                            <SelectItem value="1">Sterilizable</SelectItem>
                            <SelectItem value="0">
                                Not sterilizable
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() => {
                                setQ('');
                                router.get(
                                    products.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['products', 'filters'],
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
                                <TableHead>Reference</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead className="w-24">Unit</TableHead>
                                <TableHead className="w-32">
                                    Sterilizable
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
                                        {Array.from({ length: 5 }).map(
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
                                productsPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No products match these filters.'
                                                : canCreate
                                                    ? 'No products have been added yet — add one to get started.'
                                                    : 'No products have been added yet.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                productsPage.data.map((product) => {
                                    const category = categories.find(
                                        (c) => c.id === product.category_id,
                                    );

                                    return (
                                        <TableRow
                                            key={product.id}
                                            className="h-10 cursor-pointer hover:bg-muted/40"
                                            onClick={() =>
                                                router.visit(
                                                    products.show(product.id)
                                                        .url,
                                                )
                                            }
                                        >
                                            <TableCell>
                                                <Link
                                                    href={
                                                        products.show(
                                                            product.id,
                                                        ).url
                                                    }
                                                    className="hover:underline"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {product.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {product.reference}
                                            </TableCell>
                                            <TableCell>
                                                {category?.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {product.unit}
                                            </TableCell>
                                            <TableCell>
                                                {product.is_sterilizable
                                                    ? 'Yes'
                                                    : 'No'}
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
                        disabled={!productsPage.prev_page_url}
                        onClick={() => goTo(productsPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!productsPage.next_page_url}
                        onClick={() => goTo(productsPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Products', href: products.index() },
    ],
};
