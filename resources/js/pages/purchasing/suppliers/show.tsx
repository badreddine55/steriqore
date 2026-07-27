import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Mono } from '@/components/mono';
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
import { dashboard } from '@/routes';
import suppliers from '@/routes/suppliers';
import supplierProducts from '@/routes/suppliers/products';
import type { Option, Supplier, SupplierProduct } from '@/types';

function EditSupplierDialog({ supplier }: { supplier: Supplier }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: supplier.name,
        email: supplier.email ?? '',
        phone: supplier.phone ?? '',
        address: supplier.address ?? '',
    });

    function submit() {
        form.patch(suppliers.update(supplier.id).url, {
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
                    <DialogTitle>Edit {supplier.name}</DialogTitle>
                    <DialogDescription>
                        Update this supplier&apos;s details.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit-supplier-name">Name</Label>
                        <Input
                            id="edit-supplier-name"
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
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-supplier-email">Email</Label>
                            <Input
                                id="edit-supplier-email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                            {form.errors.email && (
                                <p className="text-sm text-destructive">
                                    {form.errors.email}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-supplier-phone">Phone</Label>
                            <Input
                                id="edit-supplier-phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-supplier-address">Address</Label>
                        <Textarea
                            id="edit-supplier-address"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
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

function DeleteSupplierDialog({ supplier }: { supplier: Supplier }) {
    const [open, setOpen] = useState(false);

    function confirmDelete() {
        router.delete(suppliers.destroy(supplier.id).url);
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
                    <DialogTitle>Delete {supplier.name}?</DialogTitle>
                    <DialogDescription>
                        This removes it from the active supplier list. Past
                        purchase orders and stock movements keep pointing to
                        it — nothing else is affected.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button variant="destructive" onClick={confirmDelete}>
                        Delete supplier
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function LinkProductForm({
    supplier,
    products,
}: {
    supplier: Supplier;
    products: Option[];
}) {
    const form = useForm({
        product_id: '',
        supplier_reference: '',
        pack_size: 1,
        price: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(supplierProducts.store(supplier.id).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <div>
                <Label
                    htmlFor="link-product"
                    className="mb-1 block text-xs text-muted-foreground"
                >
                    Product
                </Label>
                <Select
                    value={form.data.product_id}
                    onValueChange={(value) =>
                        form.setData('product_id', value)
                    }
                >
                    <SelectTrigger id="link-product" className="h-8 w-52">
                        <SelectValue placeholder="Select a product…" />
                    </SelectTrigger>
                    <SelectContent>
                        {products.map((product) => (
                            <SelectItem key={product.id} value={product.id}>
                                {product.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div>
                <Label
                    htmlFor="link-reference"
                    className="mb-1 block text-xs text-muted-foreground"
                >
                    Supplier reference
                </Label>
                <Input
                    id="link-reference"
                    value={form.data.supplier_reference}
                    onChange={(e) =>
                        form.setData('supplier_reference', e.target.value)
                    }
                    className="h-8 w-36"
                />
            </div>
            <div>
                <Label
                    htmlFor="link-pack-size"
                    className="mb-1 block text-xs text-muted-foreground"
                >
                    Pack size
                </Label>
                <Input
                    id="link-pack-size"
                    type="number"
                    min={1}
                    value={form.data.pack_size}
                    onChange={(e) =>
                        form.setData('pack_size', Number(e.target.value))
                    }
                    className="h-8 w-20"
                />
            </div>
            <div>
                <Label
                    htmlFor="link-price"
                    className="mb-1 block text-xs text-muted-foreground"
                >
                    Price
                </Label>
                <Input
                    id="link-price"
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.data.price}
                    onChange={(e) => form.setData('price', e.target.value)}
                    className="h-8 w-24"
                />
            </div>
            <Button
                type="submit"
                size="sm"
                disabled={form.processing || !form.data.product_id}
            >
                Link product
            </Button>
        </form>
    );
}

export default function SupplierShow({
    supplier,
    supplierProducts: links,
    products,
    canManage,
    canDelete,
}: {
    supplier: Supplier;
    supplierProducts: SupplierProduct[];
    products: Option[];
    canManage: boolean;
    canDelete: boolean;
}) {
    return (
        <>
            <Head title={supplier.name} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <Link
                        href={suppliers.index().url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to suppliers
                    </Link>
                    <div className="flex items-center justify-between">
                        <h1 className="text-lg font-semibold tracking-tight">
                            {supplier.name}
                        </h1>
                        <div className="flex gap-2">
                            {canManage && (
                                <EditSupplierDialog supplier={supplier} />
                            )}
                            {canDelete && (
                                <DeleteSupplierDialog supplier={supplier} />
                            )}
                        </div>
                    </div>
                </div>

                <Card>
                    <CardContent className="py-4">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm md:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">
                                    Email
                                </dt>
                                <dd>{supplier.email ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Phone
                                </dt>
                                <dd>{supplier.phone ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Address
                                </dt>
                                <dd>{supplier.address ?? '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">
                            Supplier references
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {links.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No products are linked to this supplier yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>
                                            Supplier reference
                                        </TableHead>
                                        <TableHead>Pack size</TableHead>
                                        <TableHead>Price</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {links.map((link) => {
                                        const product = products.find(
                                            (p) => p.id === link.product_id,
                                        );

                                        return (
                                            <TableRow key={link.id}>
                                                <TableCell>
                                                    {product?.label ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <Mono className="text-xs">
                                                        {link.supplier_reference ??
                                                            '—'}
                                                    </Mono>
                                                </TableCell>
                                                <TableCell>
                                                    <Mono>
                                                        {link.pack_size}
                                                    </Mono>
                                                </TableCell>
                                                <TableCell>
                                                    {link.price ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                        {canManage && (
                            <LinkProductForm
                                supplier={supplier}
                                products={products}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SupplierShow.layout = (props: { supplier: Supplier }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Suppliers', href: suppliers.index() },
        {
            title: props.supplier.name,
            href: suppliers.show(props.supplier.id),
        },
    ],
});
