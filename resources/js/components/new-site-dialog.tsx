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
import sites from '@/routes/sites';

export function NewSiteDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        address_line1: '',
        city: '',
        postal_code: '',
        country_code: '',
        timezone: 'Europe/Paris',
    });

    function submit() {
        form.post(sites.store().url, {
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
                    New site
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a site</DialogTitle>
                    <DialogDescription>
                        A physical practice location — rooms and storage
                        locations nest under it.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="site-name">Name</Label>
                        <Input
                            id="site-name"
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
                        <Label htmlFor="site-address">
                            Address (optional)
                        </Label>
                        <Input
                            id="site-address"
                            value={form.data.address_line1}
                            onChange={(e) =>
                                form.setData('address_line1', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="site-city">City (optional)</Label>
                            <Input
                                id="site-city"
                                value={form.data.city}
                                onChange={(e) =>
                                    form.setData('city', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="site-postal-code">
                                Postal code (optional)
                            </Label>
                            <Input
                                id="site-postal-code"
                                value={form.data.postal_code}
                                onChange={(e) =>
                                    form.setData('postal_code', e.target.value)
                                }
                            />
                        </div>
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
                        Add site
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
