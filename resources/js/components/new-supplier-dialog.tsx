import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import suppliers from '@/routes/suppliers';

export function NewSupplierDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', email: '', phone: '', address: '' });

    function submit() {
        form.post(suppliers.store().url, {
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
                    New supplier
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a supplier</DialogTitle>
                    <DialogDescription>
                        A vendor you buy sterilization consumables or
                        equipment from.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="supplier-name">Name</Label>
                        <Input
                            id="supplier-name"
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
                            <Label htmlFor="supplier-email">
                                Email (optional)
                            </Label>
                            <Input
                                id="supplier-email"
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
                            <Label htmlFor="supplier-phone">
                                Phone (optional)
                            </Label>
                            <Input
                                id="supplier-phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="supplier-address">
                            Address (optional)
                        </Label>
                        <Textarea
                            id="supplier-address"
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
                    <Button
                        onClick={submit}
                        disabled={form.processing || !form.data.name.trim()}
                    >
                        Add supplier
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
