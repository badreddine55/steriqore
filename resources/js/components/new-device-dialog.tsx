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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { deviceKindLabel } from '@/lib/status';
import devices from '@/routes/devices';
import type { Option } from '@/types';

const KIND_OPTIONS = Object.keys(deviceKindLabel);

export function NewDeviceDialog({ sites }: { sites: Option[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        site_id: '',
        name: '',
        serial_number: '',
        kind: 'autoclave',
        manufacturer: '',
        model: '',
    });

    function submit() {
        form.post(devices.store().url, {
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
                    New device
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a device</DialogTitle>
                    <DialogDescription>
                        Register a new autoclave, washer-disinfector, sealer
                        or other piece of equipment.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="site_id">Site</Label>
                        <Select
                            value={form.data.site_id}
                            onValueChange={(value) =>
                                form.setData('site_id', value)
                            }
                        >
                            <SelectTrigger id="site_id" className="w-full">
                                <SelectValue placeholder="Select a site…" />
                            </SelectTrigger>
                            <SelectContent>
                                {sites.map((site) => (
                                    <SelectItem key={site.id} value={site.id}>
                                        {site.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.site_id && (
                            <p className="text-sm text-destructive">
                                {form.errors.site_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. Autoclave 1"
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="serial_number">Serial number</Label>
                        <Input
                            id="serial_number"
                            value={form.data.serial_number}
                            onChange={(e) =>
                                form.setData('serial_number', e.target.value)
                            }
                        />
                        {form.errors.serial_number && (
                            <p className="text-sm text-destructive">
                                {form.errors.serial_number}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="kind">Kind</Label>
                        <Select
                            value={form.data.kind}
                            onValueChange={(value) =>
                                form.setData('kind', value)
                            }
                        >
                            <SelectTrigger id="kind" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {KIND_OPTIONS.map((kind) => (
                                    <SelectItem key={kind} value={kind}>
                                        {deviceKindLabel[kind]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="manufacturer">
                                Manufacturer (optional)
                            </Label>
                            <Input
                                id="manufacturer"
                                value={form.data.manufacturer}
                                onChange={(e) =>
                                    form.setData(
                                        'manufacturer',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="model">Model (optional)</Label>
                            <Input
                                id="model"
                                value={form.data.model}
                                onChange={(e) =>
                                    form.setData('model', e.target.value)
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
                        disabled={
                            form.processing ||
                            !form.data.site_id ||
                            !form.data.name ||
                            !form.data.serial_number
                        }
                    >
                        Add device
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
