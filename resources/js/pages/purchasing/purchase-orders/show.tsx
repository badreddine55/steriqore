import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { purchaseOrderStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import purchaseOrders from '@/routes/purchase-orders';
import goodsReceipts from '@/routes/purchase-orders/receipts';
import type { GoodsReceipt, Option, PurchaseOrder } from '@/types';

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

type LineReceiptDraft = {
    batch_number: string;
    expiry_date: string;
    qty: number;
    discrepancy_reason: string;
};

function ReceiveGoodsForm({
    purchaseOrder,
    products,
    locations,
}: {
    purchaseOrder: PurchaseOrder;
    products: Option[];
    locations: Option[];
}) {
    const remainingLines = purchaseOrder.lines.filter(
        (line) => line.qty_received < line.qty_ordered,
    );

    const [locationId, setLocationId] = useState('');
    const [drafts, setDrafts] = useState<Record<string, LineReceiptDraft>>(
        () =>
            Object.fromEntries(
                remainingLines.map((line) => [
                    line.id,
                    {
                        batch_number: '',
                        expiry_date: '',
                        qty: line.qty_ordered - line.qty_received,
                        discrepancy_reason: '',
                    },
                ]),
            ),
    );
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function updateDraft(lineId: string, patch: Partial<LineReceiptDraft>) {
        setDrafts((prev) => ({ ...prev, [lineId]: { ...prev[lineId], ...patch } }));
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        const lines = remainingLines
            .filter((line) => drafts[line.id]?.batch_number.trim())
            .map((line) => {
                const draft = drafts[line.id];

                return {
                    purchase_order_line_id: line.id,
                    batch_number: draft.batch_number,
                    expiry_date: draft.expiry_date || null,
                    qty: draft.qty,
                    discrepancy_reason: draft.discrepancy_reason || null,
                };
            });

        setProcessing(true);
        router.post(
            goodsReceipts.store(purchaseOrder.id).url,
            { location_id: locationId, lines },
            {
                preserveScroll: true,
                onError: (formErrors) =>
                    setErrors(formErrors as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    }

    if (locations.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">Receive goods</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No storage locations are configured for this practice
                        yet, so there&apos;s nowhere to receive stock into.
                        Ask an admin to set one up before goods can be
                        received.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const hasAnyBatch = remainingLines.some((line) =>
        drafts[line.id]?.batch_number.trim(),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">Receive goods</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid max-w-sm gap-2">
                        <Label htmlFor="receipt-location">
                            Storage location
                        </Label>
                        <Select
                            value={locationId}
                            onValueChange={setLocationId}
                        >
                            <SelectTrigger
                                id="receipt-location"
                                className="w-full"
                            >
                                <SelectValue placeholder="Select a location…" />
                            </SelectTrigger>
                            <SelectContent>
                                {locations.map((location) => (
                                    <SelectItem
                                        key={location.id}
                                        value={location.id}
                                    >
                                        {location.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.location_id && (
                            <p className="text-sm text-destructive">
                                {errors.location_id}
                            </p>
                        )}
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead className="w-24">
                                    Remaining
                                </TableHead>
                                <TableHead className="w-36">
                                    Batch number
                                </TableHead>
                                <TableHead className="w-36">
                                    Expiry date
                                </TableHead>
                                <TableHead className="w-24">
                                    Qty received
                                </TableHead>
                                <TableHead>Discrepancy reason</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {remainingLines.map((line) => {
                                const product = products.find(
                                    (p) => p.id === line.product_id,
                                );
                                const remaining =
                                    line.qty_ordered - line.qty_received;
                                const draft = drafts[line.id];

                                return (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            {product?.label ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Mono>{remaining}</Mono>
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                value={draft.batch_number}
                                                onChange={(e) =>
                                                    updateDraft(line.id, {
                                                        batch_number:
                                                            e.target.value,
                                                    })
                                                }
                                                aria-label={`Batch number for ${product?.label ?? 'line'}`}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="date"
                                                value={draft.expiry_date}
                                                onChange={(e) =>
                                                    updateDraft(line.id, {
                                                        expiry_date:
                                                            e.target.value,
                                                    })
                                                }
                                                aria-label={`Expiry date for ${product?.label ?? 'line'}`}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={remaining}
                                                value={draft.qty}
                                                onChange={(e) =>
                                                    updateDraft(line.id, {
                                                        qty: Number(
                                                            e.target.value,
                                                        ),
                                                    })
                                                }
                                                aria-label={`Quantity received for ${product?.label ?? 'line'}`}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                value={
                                                    draft.discrepancy_reason
                                                }
                                                onChange={(e) =>
                                                    updateDraft(line.id, {
                                                        discrepancy_reason:
                                                            e.target.value,
                                                    })
                                                }
                                                placeholder="e.g. 2 units damaged in transit"
                                                aria-label={`Discrepancy reason for ${product?.label ?? 'line'}`}
                                            />
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>

                    <p className="text-xs text-muted-foreground">
                        Leave a line&apos;s batch number blank to skip it on
                        this receipt — a purchase order can be received
                        across several partial deliveries.
                    </p>

                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing || !locationId || !hasAnyBatch}
                    >
                        Record receipt
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function GoodsReceiptHistory({
    goodsReceipts,
    products,
}: {
    goodsReceipts: GoodsReceipt[];
    products: Option[];
}) {
    if (goodsReceipts.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">Receipt history</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {goodsReceipts.map((receipt) => (
                    <div key={receipt.id} className="space-y-2">
                        <p className="text-xs text-muted-foreground">
                            <Mono>{formatDateTime(receipt.received_at)}</Mono>
                        </p>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Qty</TableHead>
                                    <TableHead>Discrepancy</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipt.lines.map((line) => {
                                    const product = products.find(
                                        (p) => p.id === line.product_id,
                                    );

                                    return (
                                        <TableRow key={line.id}>
                                            <TableCell>
                                                {product?.label ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Mono>{line.qty}</Mono>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {line.discrepancy_reason ??
                                                    '—'}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

export default function PurchaseOrderShow({
    purchaseOrder,
    supplier,
    products,
    locations,
    goodsReceipts: receipts,
    canManage,
}: {
    purchaseOrder: PurchaseOrder;
    supplier: Option;
    products: Option[];
    locations: Option[];
    goodsReceipts: GoodsReceipt[];
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

                {canManage &&
                    (purchaseOrder.status === 'ordered' ||
                        purchaseOrder.status === 'partially_received') && (
                        <ReceiveGoodsForm
                            purchaseOrder={purchaseOrder}
                            products={products}
                            locations={locations}
                        />
                    )}

                <GoodsReceiptHistory
                    goodsReceipts={receipts}
                    products={products}
                />
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
