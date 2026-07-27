import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { purchaseOrderStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import purchaseOrders from '@/routes/purchase-orders';
import type { CursorPaginated, Option, PurchaseOrder } from '@/types';

export default function PurchaseOrdersIndex({
    purchaseOrders: purchaseOrdersPage,
    suppliers,
    canCreate,
}: {
    purchaseOrders: CursorPaginated<PurchaseOrder>;
    suppliers: Option[];
    canCreate: boolean;
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

    function goTo(url: string | null) {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['purchaseOrders'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['purchaseOrders'] });
    }

    return (
        <>
            <Head title="Purchase orders" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Purchase orders
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Orders placed with suppliers, newest first.
                        </p>
                    </div>
                    {canCreate && (
                        <div className="no-print">
                            <Link href={purchaseOrders.create().url}>
                                <Button size="sm">
                                    <Plus className="size-4" />
                                    New purchase order
                                </Button>
                            </Link>
                        </div>
                    )}
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load purchase orders — the request
                            failed.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-muted/40">
                            <TableRow>
                                <TableHead>Supplier</TableHead>
                                <TableHead className="w-40">Status</TableHead>
                                <TableHead className="w-20">Lines</TableHead>
                                <TableHead className="w-44">Ordered</TableHead>
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
                                purchaseOrdersPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {canCreate
                                                ? 'No purchase orders have been created yet — create one to get started.'
                                                : 'No purchase orders have been created yet.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                purchaseOrdersPage.data.map((order) => {
                                    const supplier = suppliers.find(
                                        (s) => s.id === order.supplier_id,
                                    );
                                    const status =
                                        purchaseOrderStatus[order.status] ??
                                        purchaseOrderStatus.draft;

                                    return (
                                        <TableRow
                                            key={order.id}
                                            className="h-10 cursor-pointer hover:bg-muted/40"
                                            onClick={() =>
                                                router.visit(
                                                    purchaseOrders.show(
                                                        order.id,
                                                    ).url,
                                                )
                                            }
                                        >
                                            <TableCell>
                                                <Link
                                                    href={
                                                        purchaseOrders.show(
                                                            order.id,
                                                        ).url
                                                    }
                                                    className="hover:underline"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {supplier?.label ?? '—'}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={status} />
                                            </TableCell>
                                            <TableCell>
                                                <Mono>
                                                    {order.lines.length}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {order.ordered_at
                                                        ? new Date(
                                                              order.ordered_at,
                                                          ).toLocaleDateString()
                                                        : '—'}
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
                        disabled={!purchaseOrdersPage.prev_page_url}
                        onClick={() => goTo(purchaseOrdersPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!purchaseOrdersPage.next_page_url}
                        onClick={() => goTo(purchaseOrdersPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

PurchaseOrdersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Purchase orders', href: purchaseOrders.index() },
    ],
};
