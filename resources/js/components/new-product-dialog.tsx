import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import products from '@/routes/products';
import type { ProductCategory } from '@/types';

export function NewProductDialog({
    categories,
}: {
    categories: ProductCategory[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        reference: '',
        category_id: '',
        unit: 'piece',
        min_threshold: 0,
        is_sterilizable: false,
        barcode: '',
    });

    function submit() {
        form.post(products.store().url, {
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
                <Button size="sm">
                    <Plus className="size-4" />
                    New product
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a product</DialogTitle>
                    <DialogDescription>
                        Anything stocked and tracked in inventory — pouches,
                        indicators, instruments, consumables.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="product-name">Name</Label>
                        <Input
                            id="product-name"
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
                        <Label htmlFor="product-reference">Reference</Label>
                        <Input
                            id="product-reference"
                            value={form.data.reference}
                            onChange={(e) =>
                                form.setData('reference', e.target.value)
                            }
                            placeholder="e.g. SKU or catalog code"
                        />
                        {form.errors.reference && (
                            <p className="text-sm text-destructive">
                                {form.errors.reference}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="product-category">
                            Category (optional)
                        </Label>
                        <Select
                            value={form.data.category_id}
                            onValueChange={(value) =>
                                form.setData('category_id', value)
                            }
                        >
                            <SelectTrigger
                                id="product-category"
                                className="w-full"
                            >
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
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
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="product-unit">Unit</Label>
                            <Input
                                id="product-unit"
                                value={form.data.unit}
                                onChange={(e) =>
                                    form.setData('unit', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product-min-threshold">
                                Low-stock threshold
                            </Label>
                            <Input
                                id="product-min-threshold"
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
                        <Label htmlFor="product-barcode">
                            Barcode (optional)
                        </Label>
                        <Input
                            id="product-barcode"
                            value={form.data.barcode}
                            onChange={(e) =>
                                form.setData('barcode', e.target.value)
                            }
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="product-sterilizable"
                            checked={form.data.is_sterilizable}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'is_sterilizable',
                                    checked === true,
                                )
                            }
                        />
                        <Label
                            htmlFor="product-sterilizable"
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
                    <Button
                        onClick={submit}
                        disabled={
                            form.processing ||
                            !form.data.name.trim() ||
                            !form.data.reference.trim()
                        }
                    >
                        Add product
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
