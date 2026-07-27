import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { Mono } from '@/components/mono';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { dashboard } from '@/routes';
import products from '@/routes/products';
import type { Option, Product } from '@/types';

function EditProductDialog({ product }: { product: Product }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: product.name,
        reference: product.reference,
        unit: product.unit,
        min_threshold: product.min_threshold,
        is_sterilizable: product.is_sterilizable,
        barcode: product.barcode ?? '',
    });

    function submit() {
        form.patch(products.update(product.id).url, {
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit {product.name}</DialogTitle>
                    <DialogDescription>
                        Update this product&apos;s details.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit-product-name">Name</Label>
                        <Input
                            id="edit-product-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-product-reference">
                            Reference
                        </Label>
                        <Input
                            id="edit-product-reference"
                            value={form.data.reference}
                            onChange={(e) =>
                                form.setData('reference', e.target.value)
                            }
                        />
                        {form.errors.reference && (
                            <p className="text-sm text-destructive">
                                {form.errors.reference}
                            </p>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-product-unit">Unit</Label>
                            <Input
                                id="edit-product-unit"
                                value={form.data.unit}
                                onChange={(e) =>
                                    form.setData('unit', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-product-min-threshold">
                                Low-stock threshold
                            </Label>
                            <Input
                                id="edit-product-min-threshold"
                                type="number"
                                min={0}
                                value={form.data.min_threshold}
                                onChange={(e) =>
                                    form.setData(
                                        'min_threshold',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-product-barcode">Barcode</Label>
                        <Input
                            id="edit-product-barcode"
                            value={form.data.barcode}
                            onChange={(e) =>
                                form.setData('barcode', e.target.value)
                            }
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="edit-product-sterilizable"
                            checked={form.data.is_sterilizable}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'is_sterilizable',
                                    checked === true,
                                )
                            }
                        />
                        <Label
                            htmlFor="edit-product-sterilizable"
                            className="font-normal"
                        >
                            This product is sterilizable
                        </Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DeleteProductDialog({ product }: { product: Product }) {
    const [open, setOpen] = useState(false);

    function confirmDelete() {
        router.delete(products.destroy(product.id).url);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="destructive">
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {product.name}?</DialogTitle>
                    <DialogDescription>
                        This removes it from the active catalog. Past stock
                        movements and cycle references keep pointing to it —
                        nothing else is affected.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button variant="destructive" onClick={confirmDelete}>
                        Delete product
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function ProductShow({
    product,
    category,
    canManage,
    canDelete,
}: {
    product: Product;
    category: Option | null;
    canManage: boolean;
    canDelete: boolean;
}) {
    return (
        <>
            <Head title={product.name} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <Link
                        href={products.index().url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to products
                    </Link>
                    <div className="flex items-center justify-between">
                        <h1 className="text-lg font-semibold tracking-tight">
                            {product.name}
                        </h1>
                        <div className="flex gap-2">
                            {canManage && (
                                <EditProductDialog product={product} />
                            )}
                            {canDelete && (
                                <DeleteProductDialog product={product} />
                            )}
                        </div>
                    </div>
                </div>

                <Card>
                    <CardContent className="py-4">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm md:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">
                                    Reference
                                </dt>
                                <dd>
                                    <Mono>{product.reference}</Mono>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Category
                                </dt>
                                <dd>{category?.label ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Unit
                                </dt>
                                <dd>{product.unit}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Low-stock threshold
                                </dt>
                                <dd>
                                    <Mono>{product.min_threshold}</Mono>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Sterilizable
                                </dt>
                                <dd>
                                    {product.is_sterilizable ? 'Yes' : 'No'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Barcode
                                </dt>
                                <dd>
                                    <Mono>{product.barcode ?? '—'}</Mono>
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ProductShow.layout = (props: { product: Product }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Products', href: products.index() },
        { title: props.product.name, href: products.show(props.product.id) },
    ],
});
