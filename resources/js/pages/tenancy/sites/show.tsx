import { Head, useForm } from '@inertiajs/react';
import { Archive, Boxes, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { Label as FieldLabel } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import rooms from '@/routes/rooms';
import sites from '@/routes/sites';
import storageLocations from '@/routes/storage-locations';
import type { Option, Room, Site, StorageLocation } from '@/types';

function EditSiteDialog({ site }: { site: Site }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: site.name,
        address_line1: site.address_line1 ?? '',
        city: site.city ?? '',
        postal_code: site.postal_code ?? '',
        timezone: site.timezone,
        is_primary: site.is_primary,
    });

    function submit() {
        form.patch(sites.update(site.id).url, {
            preserveScroll: true,
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
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit site</DialogTitle>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="edit-site-name">
                            Name
                        </FieldLabel>
                        <Input
                            id="edit-site-name"
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
                        <FieldLabel htmlFor="edit-site-address">
                            Address
                        </FieldLabel>
                        <Input
                            id="edit-site-address"
                            value={form.data.address_line1}
                            onChange={(e) =>
                                form.setData('address_line1', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="edit-site-city">
                                City
                            </FieldLabel>
                            <Input
                                id="edit-site-city"
                                value={form.data.city}
                                onChange={(e) =>
                                    form.setData('city', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="edit-site-postal-code">
                                Postal code
                            </FieldLabel>
                            <Input
                                id="edit-site-postal-code"
                                value={form.data.postal_code}
                                onChange={(e) =>
                                    form.setData('postal_code', e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_primary}
                            onChange={(e) =>
                                form.setData('is_primary', e.target.checked)
                            }
                        />
                        Primary site
                    </label>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={form.processing || !form.data.name.trim()}
                    >
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ArchiveSiteDialog({
    site,
    roomsCount,
    locationsCount,
    devicesCount,
}: {
    site: Site;
    roomsCount: number;
    locationsCount: number;
    devicesCount: number;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    function submit() {
        form.delete(sites.archive(site.id).url, {
            onSuccess: () => setOpen(false),
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
                <Button size="sm" variant="destructive">
                    Archive site
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Archive {site.name}?</DialogTitle>
                    <DialogDescription>
                        {roomsCount === 0 && locationsCount === 0 ? (
                            <>
                                This site has no rooms or storage locations
                                under it.
                            </>
                        ) : (
                            <>
                                This will also archive{' '}
                                <strong>
                                    {roomsCount} room
                                    {roomsCount === 1 ? '' : 's'}
                                </strong>{' '}
                                and{' '}
                                <strong>
                                    {locationsCount} storage location
                                    {locationsCount === 1 ? '' : 's'}
                                </strong>
                                .
                            </>
                        )}{' '}
                        {devicesCount > 0 && (
                            <>
                                {devicesCount} device
                                {devicesCount === 1 ? '' : 's'} still
                                reference this site and will keep doing so.{' '}
                            </>
                        )}
                        Nothing is deleted — past evidence keeps resolving
                        this site by identity, it's just hidden from pickers
                        from now on.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <FieldLabel htmlFor="archive-site-reason">
                        Reason
                    </FieldLabel>
                    <Textarea
                        id="archive-site-reason"
                        value={form.data.reason}
                        onChange={(e) =>
                            form.setData('reason', e.target.value)
                        }
                        placeholder="Why is this site being archived?"
                    />
                    {form.errors.reason && (
                        <p className="text-sm text-destructive">
                            {form.errors.reason}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={form.processing || !form.data.reason.trim()}
                    >
                        Archive site
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AddRoomDialog({ siteId }: { siteId: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', kind: '' });

    function submit() {
        form.post(sites.rooms.store(siteId).url, {
            preserveScroll: true,
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
                    <Plus className="size-4" />
                    Add room
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a room</DialogTitle>
                    <DialogDescription>
                        e.g. sterilization room, surgery 1, storage.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="new-room-name">Name</FieldLabel>
                        <Input
                            id="new-room-name"
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
                        <FieldLabel htmlFor="new-room-kind">
                            Kind (optional)
                        </FieldLabel>
                        <Input
                            id="new-room-kind"
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData('kind', e.target.value)
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
                        Add room
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function EditRoomDialog({
    room,
    allSites,
}: {
    room: Room;
    allSites: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: room.name,
        kind: room.kind ?? '',
        site_id: room.site_id,
    });

    function submit() {
        form.patch(rooms.update(room.id).url, {
            preserveScroll: true,
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
                <Button size="icon" variant="ghost" className="size-7">
                    <Pencil className="size-3.5" />
                    <span className="sr-only">Edit {room.name}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit room</DialogTitle>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <FieldLabel htmlFor={`edit-room-name-${room.id}`}>
                            Name
                        </FieldLabel>
                        <Input
                            id={`edit-room-name-${room.id}`}
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
                        <FieldLabel htmlFor={`edit-room-kind-${room.id}`}>
                            Kind
                        </FieldLabel>
                        <Input
                            id={`edit-room-kind-${room.id}`}
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData('kind', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel>Site</FieldLabel>
                        <Select
                            value={form.data.site_id}
                            onValueChange={(value) =>
                                form.setData('site_id', value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {allSites.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={option.id}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.site_id && (
                            <p className="text-sm text-destructive">
                                {form.errors.site_id}
                            </p>
                        )}
                        {form.data.site_id !== room.site_id && (
                            <p className="text-xs text-muted-foreground">
                                Moving to a different site is refused if any
                                of this room's storage locations has ever
                                recorded a stock movement.
                            </p>
                        )}
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
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ArchiveRoomDialog({ room }: { room: Room }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    const locationsCount = room.storage_locations_count ?? 0;

    function submit() {
        form.delete(rooms.archive(room.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
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
                <Button size="icon" variant="ghost" className="size-7">
                    <Archive className="size-3.5" />
                    <span className="sr-only">Archive {room.name}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Archive {room.name}?</DialogTitle>
                    <DialogDescription>
                        {locationsCount === 0 ? (
                            <>This room has no storage locations under it.</>
                        ) : (
                            <>
                                This will also archive{' '}
                                <strong>
                                    {locationsCount} storage location
                                    {locationsCount === 1 ? '' : 's'}
                                </strong>
                                .
                            </>
                        )}{' '}
                        Nothing is deleted — past evidence keeps resolving
                        this room by identity.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <FieldLabel htmlFor={`archive-room-reason-${room.id}`}>
                        Reason
                    </FieldLabel>
                    <Textarea
                        id={`archive-room-reason-${room.id}`}
                        value={form.data.reason}
                        onChange={(e) =>
                            form.setData('reason', e.target.value)
                        }
                    />
                    {form.errors.reason && (
                        <p className="text-sm text-destructive">
                            {form.errors.reason}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={form.processing || !form.data.reason.trim()}
                    >
                        Archive room
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function AddLocationDialog({ roomId }: { roomId: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', kind: '' });

    function submit(again: boolean) {
        form.post(rooms.storageLocations.store(roomId).url, {
            preserveScroll: true,
            onSuccess: () => {
                if (again) {
                    form.setData('name', '');
                    form.setData('kind', '');
                } else {
                    setOpen(false);
                    form.reset();
                }
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
                <Button size="sm" variant="ghost" className="h-7 text-xs">
                    <Plus className="size-3.5" />
                    Add location
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a storage location</DialogTitle>
                    <DialogDescription>
                        e.g. cupboard, drawer, fridge, shelf.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="new-location-name">
                            Name
                        </FieldLabel>
                        <Input
                            id="new-location-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            autoFocus
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="new-location-kind">
                            Kind (optional)
                        </FieldLabel>
                        <Input
                            id="new-location-kind"
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData('kind', e.target.value)
                            }
                        />
                    </div>
                </div>

                <DialogFooter className="sm:justify-between">
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Finish
                    </Button>
                    <Button
                        onClick={() => submit(true)}
                        disabled={form.processing || !form.data.name.trim()}
                    >
                        Add and add another
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function EditLocationDialog({
    location,
    roomOptions,
}: {
    location: StorageLocation;
    roomOptions: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: location.name,
        kind: location.kind ?? '',
        room_id: location.room_id ?? '',
    });

    function submit() {
        form.patch(storageLocations.update(location.id).url, {
            preserveScroll: true,
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
                <Button size="icon" variant="ghost" className="size-7">
                    <Pencil className="size-3.5" />
                    <span className="sr-only">Edit {location.name}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit storage location</DialogTitle>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <FieldLabel
                            htmlFor={`edit-location-name-${location.id}`}
                        >
                            Name
                        </FieldLabel>
                        <Input
                            id={`edit-location-name-${location.id}`}
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
                        <FieldLabel
                            htmlFor={`edit-location-kind-${location.id}`}
                        >
                            Kind
                        </FieldLabel>
                        <Input
                            id={`edit-location-kind-${location.id}`}
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData('kind', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel>Room</FieldLabel>
                        <Select
                            value={form.data.room_id}
                            onValueChange={(value) =>
                                form.setData('room_id', value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roomOptions.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={option.id}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.room_id && (
                            <p className="text-sm text-destructive">
                                {form.errors.room_id}
                            </p>
                        )}
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
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ArchiveLocationDialog({ location }: { location: StorageLocation }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    function submit() {
        form.delete(storageLocations.archive(location.id).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
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
                <Button size="icon" variant="ghost" className="size-7">
                    <Archive className="size-3.5" />
                    <span className="sr-only">
                        Archive {location.name}
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Archive {location.name}?</DialogTitle>
                    <DialogDescription>
                        {location.units_on_hand > 0 ? (
                            <>
                                This location currently holds{' '}
                                <strong>
                                    {location.units_on_hand} unit
                                    {location.units_on_hand === 1 ? '' : 's'}
                                </strong>{' '}
                                of stock. Archiving doesn't move or clear
                                it — stock records keep resolving this
                                location by identity, it's just hidden from
                                pickers for new stock from now on.
                            </>
                        ) : (
                            <>
                                This location holds no stock right now.
                                It's hidden from pickers, not deleted.
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <FieldLabel
                        htmlFor={`archive-location-reason-${location.id}`}
                    >
                        Reason
                    </FieldLabel>
                    <Textarea
                        id={`archive-location-reason-${location.id}`}
                        value={form.data.reason}
                        onChange={(e) =>
                            form.setData('reason', e.target.value)
                        }
                    />
                    {form.errors.reason && (
                        <p className="text-sm text-destructive">
                            {form.errors.reason}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={form.processing || !form.data.reason.trim()}
                    >
                        Archive location
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function SiteShow({
    site,
    rooms: roomList,
    storageLocations: locationList,
    devicesCount,
    activeBatchesCount,
    allSites,
    canManage,
}: {
    site: Site;
    rooms: Room[];
    storageLocations: StorageLocation[];
    devicesCount: number;
    activeBatchesCount: number;
    allSites: Option[];
    canManage: boolean;
}) {
    const locationsByRoom = new Map<string, StorageLocation[]>();

    for (const location of locationList) {
        if (!location.room_id) {
            continue;
        }

        const list = locationsByRoom.get(location.room_id) ?? [];
        list.push(location);
        locationsByRoom.set(location.room_id, list);
    }

    const roomOptions: Option[] = roomList.map((room) => ({
        id: room.id,
        label: room.name,
    }));

    return (
        <>
            <Head title={site.name} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-lg font-semibold tracking-tight">
                                {site.name}
                            </h1>
                            {site.is_primary && (
                                <Badge variant="secondary">Primary</Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {[
                                site.address_line1,
                                site.city,
                                site.postal_code,
                            ]
                                .filter(Boolean)
                                .join(', ') || 'No address on file'}
                            {' · '}
                            {site.timezone}
                        </p>
                    </div>
                    {canManage && (
                        <div className="no-print flex gap-2">
                            <EditSiteDialog site={site} />
                            <ArchiveSiteDialog
                                site={site}
                                roomsCount={site.rooms_count ?? 0}
                                locationsCount={
                                    site.storage_locations_count ?? 0
                                }
                                devicesCount={devicesCount}
                            />
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">
                                Rooms
                            </p>
                            <p className="text-xl font-semibold tabular-nums">
                                {site.rooms_count ?? 0}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">
                                Storage locations
                            </p>
                            <p className="text-xl font-semibold tabular-nums">
                                {site.storage_locations_count ?? 0}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">
                                Devices
                            </p>
                            <p className="text-xl font-semibold tabular-nums">
                                {devicesCount}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-xs text-muted-foreground">
                                Active batches
                            </p>
                            <p className="text-xl font-semibold tabular-nums">
                                {activeBatchesCount}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-sm">
                            Rooms &amp; storage locations
                        </CardTitle>
                        {canManage && <AddRoomDialog siteId={site.id} />}
                    </CardHeader>
                    <CardContent>
                        {roomList.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {canManage
                                    ? 'No rooms yet — add one to start placing storage locations.'
                                    : 'No rooms have been added yet.'}
                            </p>
                        ) : (
                            <div className="flex flex-col gap-3">
                                {roomList.map((room) => {
                                    const locations =
                                        locationsByRoom.get(room.id) ?? [];

                                    return (
                                        <section
                                            key={room.id}
                                            aria-label={`Room: ${room.name}`}
                                            className="rounded-md border"
                                        >
                                            <div className="flex items-center justify-between border-b bg-muted/30 px-3 py-2">
                                                <div className="flex items-center gap-2 text-sm font-medium">
                                                    {room.name}
                                                    {room.kind && (
                                                        <span className="text-xs font-normal text-muted-foreground">
                                                            {room.kind}
                                                        </span>
                                                    )}
                                                </div>
                                                {canManage && (
                                                    <div className="no-print flex items-center gap-1">
                                                        <AddLocationDialog
                                                            roomId={room.id}
                                                        />
                                                        <EditRoomDialog
                                                            room={room}
                                                            allSites={
                                                                allSites
                                                            }
                                                        />
                                                        <ArchiveRoomDialog
                                                            room={room}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                            {locations.length === 0 ? (
                                                <p className="px-3 py-2 text-sm text-muted-foreground">
                                                    No storage locations in
                                                    this room yet.
                                                </p>
                                            ) : (
                                                <ul className="divide-y">
                                                    {locations.map(
                                                        (location) => (
                                                            <li
                                                                key={
                                                                    location.id
                                                                }
                                                                aria-label={`Storage location: ${location.name}`}
                                                                className="flex items-center justify-between px-3 py-2 pl-8 text-sm"
                                                            >
                                                                <span className="flex items-center gap-2">
                                                                    <Boxes className="size-3.5 text-muted-foreground" />
                                                                    {
                                                                        location.name
                                                                    }
                                                                    {location.kind && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            (
                                                                            {
                                                                                location.kind
                                                                            }

                                                                            )
                                                                        </span>
                                                                    )}
                                                                    {location.units_on_hand >
                                                                        0 && (
                                                                        <span className="tabular-nums text-xs text-muted-foreground">
                                                                            {
                                                                                location.units_on_hand
                                                                            }{' '}
                                                                            units
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                {canManage && (
                                                                    <div className="no-print flex items-center gap-1">
                                                                        <EditLocationDialog
                                                                            location={
                                                                                location
                                                                            }
                                                                            roomOptions={
                                                                                roomOptions
                                                                            }
                                                                        />
                                                                        <ArchiveLocationDialog
                                                                            location={
                                                                                location
                                                                            }
                                                                        />
                                                                    </div>
                                                                )}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </section>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SiteShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sites', href: sites.index() },
    ],
};
