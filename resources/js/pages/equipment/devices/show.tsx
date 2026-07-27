import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock, Pencil, Trash2, XCircle } from 'lucide-react';
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
import {
    deviceKindLabel,
    deviceStatus,
    maintenanceKindLabel,
    programStatus,
} from '@/lib/status';
import { dashboard } from '@/routes';
import devices from '@/routes/devices';
import deviceMaintenanceRecords from '@/routes/devices/maintenance-records';
import deviceProgramRoutes from '@/routes/devices/programs';
import type { Device, DeviceProgram, MaintenanceRecord, Option } from '@/types';

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

function EditProgramDialog({
    device,
    program,
}: {
    device: Device;
    program: DeviceProgram;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: program.name,
        target_temperature_celsius: program.target_temperature_celsius,
        plateau_minutes: program.plateau_minutes,
        is_active: program.is_active,
    });

    function submit() {
        form.patch(
            deviceProgramRoutes.update({ device: device.id, program: program.id })
                .url,
            { onSuccess: () => setOpen(false) },
        );
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
                <Button size="icon" variant="ghost" aria-label={`Edit ${program.name}`}>
                    <Pencil className="size-4 text-muted-foreground" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit program</DialogTitle>
                    <DialogDescription>
                        Changing this only affects future cycles — past
                        cycles keep the program details they actually ran
                        with.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit-program-name">Name</Label>
                        <Input
                            id="edit-program-name"
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
                            <Label htmlFor="edit-program-temp">
                                Target temperature (°C)
                            </Label>
                            <Input
                                id="edit-program-temp"
                                type="number"
                                value={form.data.target_temperature_celsius}
                                onChange={(e) =>
                                    form.setData(
                                        'target_temperature_celsius',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-program-plateau">
                                Plateau (minutes)
                            </Label>
                            <Input
                                id="edit-program-plateau"
                                type="number"
                                value={form.data.plateau_minutes}
                                onChange={(e) =>
                                    form.setData(
                                        'plateau_minutes',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-program-active">Status</Label>
                        <Select
                            value={form.data.is_active ? 'active' : 'inactive'}
                            onValueChange={(value) =>
                                form.setData('is_active', value === 'active')
                            }
                        >
                            <SelectTrigger
                                id="edit-program-active"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Inactive
                                </SelectItem>
                            </SelectContent>
                        </Select>
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

function AddProgramForm({ device }: { device: Device }) {
    const form = useForm({
        name: '',
        target_temperature_celsius: 134,
        plateau_minutes: 18,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(deviceProgramRoutes.store(device.id).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <div>
                <Label
                    htmlFor="new-program-name"
                    className="mb-1 block text-xs text-muted-foreground"
                >
                    Name
                </Label>
                <Input
                    id="new-program-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. 134°C Standard"
                    className="h-8 w-48"
                />
            </div>
            <div>
                <Label
                    htmlFor="new-program-temp"
                    className="mb-1 block text-xs text-muted-foreground"
                >
                    Target temp (°C)
                </Label>
                <Input
                    id="new-program-temp"
                    type="number"
                    value={form.data.target_temperature_celsius}
                    onChange={(e) =>
                        form.setData(
                            'target_temperature_celsius',
                            Number(e.target.value),
                        )
                    }
                    className="h-8 w-28"
                />
            </div>
            <div>
                <Label
                    htmlFor="new-program-plateau"
                    className="mb-1 block text-xs text-muted-foreground"
                >
                    Plateau (min)
                </Label>
                <Input
                    id="new-program-plateau"
                    type="number"
                    value={form.data.plateau_minutes}
                    onChange={(e) =>
                        form.setData('plateau_minutes', Number(e.target.value))
                    }
                    className="h-8 w-24"
                />
            </div>
            <Button
                type="submit"
                size="sm"
                disabled={form.processing || !form.data.name.trim()}
            >
                Add program
            </Button>
        </form>
    );
}

const MAINTENANCE_KINDS = Object.keys(maintenanceKindLabel);

function defaultDateTimeLocal(): string {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());

    return now.toISOString().slice(0, 16);
}

/**
 * The most recent record that actually set a next-due date — not
 * necessarily the single latest record, since e.g. a corrective repair
 * visit might not reset the scheduled date a preventive visit set earlier.
 */
function nextDueStatus(records: MaintenanceRecord[]) {
    const nextDueAt = records.find((r) => r.next_due_at)?.next_due_at;

    if (!nextDueAt) {
        return null;
    }

    const isOverdue = new Date(nextDueAt) < new Date();
    const date = nextDueAt.slice(0, 10);

    return isOverdue
        ? { tone: 'danger' as const, label: `Overdue since ${date}`, icon: XCircle }
        : { tone: 'pending' as const, label: `Next due ${date}`, icon: Clock };
}

function RecordMaintenanceForm({ device }: { device: Device }) {
    const form = useForm({
        kind: 'preventive',
        technician: '',
        performed_at: defaultDateTimeLocal(),
        next_due_at: '',
        description: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(deviceMaintenanceRecords.store(device.id).url, {
            preserveScroll: true,
            onSuccess: () =>
                form.setData({
                    kind: 'preventive',
                    technician: '',
                    performed_at: defaultDateTimeLocal(),
                    next_due_at: '',
                    description: '',
                }),
        });
    }

    return (
        <form onSubmit={submit} className="space-y-2 border-t pt-3">
            <div className="flex flex-wrap items-end gap-2">
                <div>
                    <Label
                        htmlFor="maintenance-kind"
                        className="mb-1 block text-xs text-muted-foreground"
                    >
                        Kind
                    </Label>
                    <Select
                        value={form.data.kind}
                        onValueChange={(value) => form.setData('kind', value)}
                    >
                        <SelectTrigger id="maintenance-kind" className="h-8 w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {MAINTENANCE_KINDS.map((kind) => (
                                <SelectItem key={kind} value={kind}>
                                    {maintenanceKindLabel[kind]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label
                        htmlFor="maintenance-technician"
                        className="mb-1 block text-xs text-muted-foreground"
                    >
                        Technician
                    </Label>
                    <Input
                        id="maintenance-technician"
                        value={form.data.technician}
                        onChange={(e) =>
                            form.setData('technician', e.target.value)
                        }
                        className="h-8 w-40"
                    />
                </div>
                <div>
                    <Label
                        htmlFor="maintenance-performed-at"
                        className="mb-1 block text-xs text-muted-foreground"
                    >
                        Performed at
                    </Label>
                    <Input
                        id="maintenance-performed-at"
                        type="datetime-local"
                        value={form.data.performed_at}
                        onChange={(e) =>
                            form.setData('performed_at', e.target.value)
                        }
                        className="h-8"
                    />
                </div>
                <div>
                    <Label
                        htmlFor="maintenance-next-due"
                        className="mb-1 block text-xs text-muted-foreground"
                    >
                        Next due (optional)
                    </Label>
                    <Input
                        id="maintenance-next-due"
                        type="datetime-local"
                        value={form.data.next_due_at}
                        onChange={(e) =>
                            form.setData('next_due_at', e.target.value)
                        }
                        className="h-8"
                    />
                </div>
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing}
                >
                    Record maintenance
                </Button>
            </div>
            <Textarea
                value={form.data.description}
                onChange={(e) => form.setData('description', e.target.value)}
                placeholder="Notes (optional)"
                aria-label="Maintenance notes"
                className="min-h-16"
            />
            {form.errors.next_due_at && (
                <p className="text-sm text-destructive">
                    {form.errors.next_due_at}
                </p>
            )}
        </form>
    );
}

export default function DeviceShow({
    device,
    site,
    programs,
    maintenanceRecords,
    canManage,
}: {
    device: Device;
    site: Option;
    programs: DeviceProgram[];
    maintenanceRecords: MaintenanceRecord[];
    canManage: boolean;
}) {
    const status = deviceStatus[device.status] ?? deviceStatus.active;
    const dueStatus = nextDueStatus(maintenanceRecords);

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
                            {dueStatus && <StatusBadge status={dueStatus} />}
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

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Programs</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {programs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No programs configured for this device yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Target temp</TableHead>
                                        <TableHead>Plateau</TableHead>
                                        <TableHead className="w-24">
                                            Status
                                        </TableHead>
                                        {canManage && (
                                            <TableHead className="w-20" />
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {programs.map((program) => (
                                        <TableRow key={program.id}>
                                            <TableCell>
                                                {program.name}
                                            </TableCell>
                                            <TableCell>
                                                <Mono>
                                                    {
                                                        program.target_temperature_celsius
                                                    }
                                                    °C
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <Mono>
                                                    {program.plateau_minutes}{' '}
                                                    min
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        program.is_active
                                                            ? programStatus.active
                                                            : programStatus.inactive
                                                    }
                                                />
                                            </TableCell>
                                            {canManage && (
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <EditProgramDialog
                                                            device={device}
                                                            program={program}
                                                        />
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Remove ${program.name}`}
                                                            onClick={() =>
                                                                router.delete(
                                                                    deviceProgramRoutes.destroy(
                                                                        {
                                                                            device: device.id,
                                                                            program:
                                                                                program.id,
                                                                        },
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4 text-muted-foreground" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        {canManage && <AddProgramForm device={device} />}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Maintenance</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {maintenanceRecords.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No maintenance has been recorded for this
                                device yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Kind</TableHead>
                                        <TableHead>Technician</TableHead>
                                        <TableHead>Performed</TableHead>
                                        <TableHead>Next due</TableHead>
                                        <TableHead>Notes</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {maintenanceRecords.map((record) => (
                                        <TableRow key={record.id}>
                                            <TableCell>
                                                {maintenanceKindLabel[
                                                    record.kind
                                                ] ?? record.kind}
                                            </TableCell>
                                            <TableCell>
                                                {record.technician ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {formatDateTime(
                                                        record.performed_at,
                                                    )}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {record.next_due_at
                                                        ? formatDateTime(
                                                              record.next_due_at,
                                                          )
                                                        : '—'}
                                                </Mono>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {record.description ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        {canManage && (
                            <RecordMaintenanceForm device={device} />
                        )}
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
