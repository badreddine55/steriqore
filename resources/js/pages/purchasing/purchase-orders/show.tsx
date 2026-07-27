import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { Mono } from '@/components/mono';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { purchaseOrderStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import purchaseOrders from '@/routes/purchase-orders';
import type { Option, PurchaseOrder } from '@/types';

function CancelPurchaseOrderDialog({
    purchaseOrder,
}: {
    purchaseOrder: PurchaseOrder;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    function submit() {
        form.post(purchaseOrders.cancel(purchaseOrder.id).url, {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="destructive">
                    Cancel purchase order
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel this purchase order?</DialogTitle>
                    <DialogDescription>
                        This is permanent — a cancelled order can&apos;t be
                        reopened.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <Label htmlFor="cancel-reason">
                        Reason (optional)
                    </Label>
                    <Textarea
                        id="cancel-reason"
                        value={form.data.reason}
                        onChange={(e) =>
                            form.setData('reason', e.target.value)
                        }
                        placeholder="e.g. Supplier out of stock, ordered elsewhere."
                    />
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Keep order
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={form.processing}
                    >
                        Cancel purchase order
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function PurchaseOrderShow({
    purchaseOrder,
    supplier,
    products,
    canManage,
}: {
    purchaseOrder: PurchaseOrder;
    supplier: Option;
    products: Option[];
    canManage: boolean;
}) {
    const status =
        purchaseOrderStatus[purchaseOrder.status] ?? purchaseOrderStatus.draft;

    return (
        <>
            <Head title={`Purchase order — ${supplier.label}`} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <Link
                        href={purchaseOrders.index().url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to purchase orders
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <h1 className="text-lg font-semibold tracking-tight">
                                {supplier.label}
                            </h1>
                            <StatusBadge status={status} />
                        </div>
                        {canManage && (
                            <div className="flex gap-2">
                                {purchaseOrder.status === 'draft' && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                purchaseOrders.order(
                                                    purchaseOrder.id,
                                                ).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Mark as ordered
                                    </Button>
                                )}
                                {(purchaseOrder.status === 'draft' ||
                                    purchaseOrder.status === 'ordered') && (
                                    <CancelPurchaseOrderDialog
                                        purchaseOrder={purchaseOrder}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Lines</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Ordered</TableHead>
                                    <TableHead>Received</TableHead>
                                    <TableHead>Unit price</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {purchaseOrder.lines.map((line) => {
                                    const product = products.find(
                                        (p) => p.id === line.product_id,
                                    );

                                    return (
                                        <TableRow key={line.id}>
                                            <TableCell>
                                                {product?.label ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Mono>
                                                    {line.qty_ordered}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <Mono>
                                                    {line.qty_received}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                {line.unit_price ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PurchaseOrderShow.layout = (props: {
    purchaseOrder: PurchaseOrder;
    supplier: Option;
}) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Purchase orders', href: purchaseOrders.index() },
        {
            title: props.supplier.label,
            href: purchaseOrders.show(props.purchaseOrder.id),
        },
    ],
});
