import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { dashboard } from '@/routes';
import purchaseOrders from '@/routes/purchase-orders';
import type { Option } from '@/types';

type LineDraft = {
    product_id: string;
    qty_ordered: number;
    unit_price: string;
};

function emptyLine(): LineDraft {
    return { product_id: '', qty_ordered: 1, unit_price: '' };
}

export default function PurchaseOrderCreate({
    suppliers,
    products,
}: {
    suppliers: Option[];
    products: Option[];
}) {
    const form = useForm({
        supplier_id: '',
        lines: [emptyLine()] as LineDraft[],
    });

    function updateLine(index: number, patch: Partial<LineDraft>) {
        const lines = form.data.lines.map((line, i) =>
            i === index ? { ...line, ...patch } : line,
        );
        form.setData('lines', lines);
    }

    function addLine() {
        form.setData('lines', [...form.data.lines, emptyLine()]);
    }

    function removeLine(index: number) {
        form.setData(
            'lines',
            form.data.lines.filter((_, i) => i !== index),
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(purchaseOrders.store().url);
    }

    const hasValidLines =
        form.data.lines.length > 0 &&
        form.data.lines.every(
            (line) => line.product_id && line.qty_ordered > 0,
        );

    return (
        <>
            <Head title="New purchase order" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <Link
                        href={purchaseOrders.index().url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to purchase orders
                    </Link>
                    <h1 className="text-lg font-semibold tracking-tight">
                        New purchase order
                    </h1>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Supplier
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid max-w-sm gap-2">
                                <Label htmlFor="supplier_id">Supplier</Label>
                                <Select
                                    value={form.data.supplier_id}
                                    onValueChange={(value) =>
                                        form.setData('supplier_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="supplier_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a supplier…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {suppliers.map((supplier) => (
                                            <SelectItem
                                                key={supplier.id}
                                                value={supplier.id}
                                            >
                                                {supplier.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.supplier_id && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.supplier_id}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Lines</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead className="w-28">
                                            Quantity
                                        </TableHead>
                                        <TableHead className="w-32">
                                            Unit price
                                        </TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {form.data.lines.map((line, index) => (
                                         
                                        <TableRow key={index}>
                                            <TableCell>
                                                <Select
                                                    value={line.product_id}
                                                    onValueChange={(value) =>
                                                        updateLine(index, {
                                                            product_id: value,
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger
                                                        className="w-full"
                                                        aria-label={`Product for line ${index + 1}`}
                                                    >
                                                        <SelectValue placeholder="Select a product…" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {products.map(
                                                            (product) => (
                                                                <SelectItem
                                                                    key={
                                                                        product.id
                                                                    }
                                                                    value={
                                                                        product.id
                                                                    }
                                                                >
                                                                    {
                                                                        product.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={line.qty_ordered}
                                                    onChange={(e) =>
                                                        updateLine(index, {
                                                            qty_ordered:
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                        })
                                                    }
                                                    aria-label={`Quantity for line ${index + 1}`}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    value={line.unit_price}
                                                    onChange={(e) =>
                                                        updateLine(index, {
                                                            unit_price:
                                                                e.target
                                                                    .value,
                                                        })
                                                    }
                                                    aria-label={`Unit price for line ${index + 1}`}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Remove line ${index + 1}`}
                                                    disabled={
                                                        form.data.lines
                                                            .length === 1
                                                    }
                                                    onClick={() =>
                                                        removeLine(index)
                                                    }
                                                >
                                                    <Trash2 className="size-4 text-muted-foreground" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addLine}
                            >
                                <Plus className="size-4" />
                                Add line
                            </Button>
                        </CardContent>
                    </Card>

                    <div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.supplier_id ||
                                !hasValidLines
                            }
                        >
                            Create purchase order
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

PurchaseOrderCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Purchase orders', href: purchaseOrders.index() },
        { title: 'New', href: purchaseOrders.create() },
    ],
};
