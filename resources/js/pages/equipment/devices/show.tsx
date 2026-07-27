import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { Mono } from '@/components/mono';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { deviceKindLabel, deviceStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import devices from '@/routes/devices';
import type { Device, Option } from '@/types';

const STATUS_OPTIONS = Object.keys(deviceStatus);
const KIND_OPTIONS = Object.keys(deviceKindLabel);

function EditDeviceDialog({ device }: { device: Device }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: device.name,
        serial_number: device.serial_number,
        kind: device.kind,
        status: device.status,
        manufacturer: device.manufacturer ?? '',
        model: device.model ?? '',
        commissioned_at: device.commissioned_at ?? '',
        decommissioned_at: device.decommissioned_at ?? '',
        notes: device.notes ?? '',
    });

    function submit() {
        form.patch(devices.update(device.id).url, {
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
                    <DialogTitle>Edit {device.name}</DialogTitle>
                    <DialogDescription>
                        Update this device&apos;s details.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit-name">Name</Label>
                        <Input
                            id="edit-name"
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
                        <Label htmlFor="edit-serial_number">
                            Serial number
                        </Label>
                        <Input
                            id="edit-serial_number"
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

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-kind">Kind</Label>
                            <Select
                                value={form.data.kind}
                                onValueChange={(value) =>
                                    form.setData('kind', value)
                                }
                            >
                                <SelectTrigger
                                    id="edit-kind"
                                    className="w-full"
                                >
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
                        <div className="grid gap-2">
                            <Label htmlFor="edit-status">Status</Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData('status', value)
                                }
                            >
                                <SelectTrigger
                                    id="edit-status"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((status) => (
                                        <SelectItem
                                            key={status}
                                            value={status}
                                        >
                                            {deviceStatus[status].label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-manufacturer">
                                Manufacturer
                            </Label>
                            <Input
                                id="edit-manufacturer"
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
                            <Label htmlFor="edit-model">Model</Label>
                            <Input
                                id="edit-model"
                                value={form.data.model}
                                onChange={(e) =>
                                    form.setData('model', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-commissioned_at">
                                Commissioned
                            </Label>
                            <Input
                                id="edit-commissioned_at"
                                type="date"
                                value={form.data.commissioned_at}
                                onChange={(e) =>
                                    form.setData(
                                        'commissioned_at',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-decommissioned_at">
                                Decommissioned
                            </Label>
                            <Input
                                id="edit-decommissioned_at"
                                type="date"
                                value={form.data.decommissioned_at}
                                onChange={(e) =>
                                    form.setData(
                                        'decommissioned_at',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-notes">Notes</Label>
                        <Textarea
                            id="edit-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
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

export default function DeviceShow({
    device,
    site,
    canManage,
}: {
    device: Device;
    site: Option;
    canManage: boolean;
}) {
    const status = deviceStatus[device.status] ?? deviceStatus.active;

    return (
        <>
            <Head title={device.name} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <Link
                        href={devices.index().url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to devices
                    </Link>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <h1 className="text-lg font-semibold tracking-tight">
                                {device.name}
                            </h1>
                            <StatusBadge status={status} />
                        </div>
                        {canManage && <EditDeviceDialog device={device} />}
                    </div>
                </div>

                <Card>
                    <CardContent className="py-4">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm md:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">
                                    Site
                                </dt>
                                <dd>{site.label}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Kind
                                </dt>
                                <dd>
                                    {deviceKindLabel[device.kind] ??
                                        device.kind}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Serial number
                                </dt>
                                <dd>
                                    <Mono>{device.serial_number}</Mono>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Manufacturer
                                </dt>
                                <dd>{device.manufacturer ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Model
                                </dt>
                                <dd>{device.model ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Commissioned
                                </dt>
                                <dd>
                                    <Mono>
                                        {device.commissioned_at ?? '—'}
                                    </Mono>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Decommissioned
                                </dt>
                                <dd>
                                    <Mono>
                                        {device.decommissioned_at ?? '—'}
                                    </Mono>
                                </dd>
                            </div>
                            {device.notes && (
                                <div className="col-span-2 md:col-span-3">
                                    <dt className="text-muted-foreground">
                                        Notes
                                    </dt>
                                    <dd>{device.notes}</dd>
                                </div>
                            )}
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DeviceShow.layout = (props: { device: Device }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Devices', href: devices.index() },
        { title: props.device.name, href: devices.show(props.device.id) },
    ],
});
