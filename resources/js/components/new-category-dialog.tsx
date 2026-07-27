import { useForm } from '@inertiajs/react';
import { FolderPlus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
import productCategories from '@/routes/product-categories';
import type { ProductCategory } from '@/types';

export function NewCategoryDialog({
    categories,
}: {
    categories: ProductCategory[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', parent_category_id: '' });

    function submit() {
        form.post(productCategories.store().url, {
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
                <Button size="sm" variant="outline">
                    <FolderPlus className="size-4" />
                    New category
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a category</DialogTitle>
                    <DialogDescription>
                        Categories group products for filtering — optionally
                        nested under a parent.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="category-name">Name</Label>
                        <Input
                            id="category-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Sterilization pouches"
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category-parent">
                            Parent category (optional)
                        </Label>
                        <Select
                            value={form.data.parent_category_id}
                            onValueChange={(value) =>
                                form.setData('parent_category_id', value)
                            }
                        >
                            <SelectTrigger id="category-parent" className="w-full">
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
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={form.processing || !form.data.name.trim()}
                    >
                        Add category
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
