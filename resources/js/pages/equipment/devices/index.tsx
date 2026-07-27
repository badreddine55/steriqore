import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NewDeviceDialog } from '@/components/new-device-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { deviceKindLabel, deviceStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import devices from '@/routes/devices';
import type { CursorPaginated, Device, DeviceFilters, Option } from '@/types';

const STATUS_OPTIONS = Object.keys(deviceStatus);
const KIND_OPTIONS = Object.keys(deviceKindLabel);

export default function DevicesIndex({
    devices: devicesPage,
    sites,
    canCreate,
    filters,
}: {
    devices: CursorPaginated<Device>;
    sites: Option[];
    canCreate: boolean;
    filters: DeviceFilters;
}) {
    const [q, setQ] = useState(filters.name ?? '');
    const [isLoading, setIsLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', () => setIsLoading(true));
        const removeFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    useEffect(() => {
        const handle = setTimeout(() => {
            if (q !== (filters.name ?? '')) {
                applyFilters({ name: q || undefined });
            }
        }, 300);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    function applyFilters(next: Partial<DeviceFilters>) {
        const merged: Record<string, string> = {
            ...filters,
            ...next,
        } as Record<string, string>;

        for (const [key, value] of Object.entries(merged)) {
            if (!value || value === 'all') {
                delete merged[key];
            }
        }

        router.get(
            devices.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['devices', 'filters'],
                onError: () => setLoadError(true),
                onSuccess: () => setLoadError(false),
            },
        );
    }

    function goTo(url: string | null) {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['devices', 'filters'],
            onError: () => setLoadError(true),
            onSuccess: () => setLoadError(false),
        });
    }

    function retry() {
        setLoadError(false);
        router.reload({ only: ['devices', 'filters'] });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Devices" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Devices
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Every autoclave, washer-disinfector, sealer and
                            other piece of sterilization equipment.
                        </p>
                    </div>
                    {canCreate && (
                        <div className="no-print">
                            <NewDeviceDialog sites={sites} />
                        </div>
                    )}
                </div>

                {loadError && (
                    <div className="flex items-center justify-between gap-3 rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                        <span className="flex items-center gap-2">
                            <AlertCircle className="size-4" />
                            Couldn&apos;t load devices — the request failed.
                            Your filters weren&apos;t lost.
                        </span>
                        <Button variant="outline" size="sm" onClick={retry}>
                            Retry
                        </Button>
                    </div>
                )}

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search by name…"
                        className="h-8 w-48"
                        aria-label="Search by name"
                    />

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ status: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUS_OPTIONS.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {deviceStatus[status].label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.kind ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ kind: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Kind" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All kinds</SelectItem>
                            {KIND_OPTIONS.map((kind) => (
                                <SelectItem key={kind} value={kind}>
                                    {deviceKindLabel[kind]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.site_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ site_id: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44">
                            <SelectValue placeholder="Site" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All sites</SelectItem>
                            {sites.map((site) => (
                                <SelectItem key={site.id} value={site.id}>
                                    {site.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() => {
                                setQ('');
                                router.get(
                                    devices.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['devices', 'filters'],
                                    },
                                );
                            }}
                        >
                            <X className="size-3.5" />
                            Clear filters
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-muted/40">
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Site</TableHead>
                                <TableHead>Kind</TableHead>
                                <TableHead className="w-32">Status</TableHead>
                                <TableHead>Serial number</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({ length: 5 }).map(
                                            (__, j) => (
                                                <TableCell key={j}>
                                                    <Skeleton className="h-4 w-full max-w-32" />
                                                </TableCell>
                                            ),
                                        )}
                                    </TableRow>
                                ))}
                            {!isLoading &&
                                !loadError &&
                                devicesPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No devices match these filters.'
                                                : canCreate
                                                    ? 'No devices have been registered yet — add one to get started.'
                                                    : 'No devices have been registered yet.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                devicesPage.data.map((device) => {
                                    const site = sites.find(
                                        (s) => s.id === device.site_id,
                                    );
                                    const status =
                                        deviceStatus[device.status] ??
                                        deviceStatus.active;

                                    return (
                                        <TableRow
                                            key={device.id}
                                            className="h-10 cursor-pointer hover:bg-muted/40"
                                            onClick={() =>
                                                router.visit(
                                                    devices.show(device.id)
                                                        .url,
                                                )
                                            }
                                        >
                                            <TableCell>
                                                <Link
                                                    href={
                                                        devices.show(
                                                            device.id,
                                                        ).url
                                                    }
                                                    className="hover:underline"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {device.name}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {site?.label ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {deviceKindLabel[
                                                    device.kind
                                                ] ?? device.kind}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={status} />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {device.serial_number}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                        </TableBody>
                    </Table>
                </div>

                <div className="no-print flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!devicesPage.prev_page_url}
                        onClick={() => goTo(devicesPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!devicesPage.next_page_url}
                        onClick={() => goTo(devicesPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

DevicesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Devices', href: devices.index() },
    ],
};
