import { Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { NewSiteDialog } from '@/components/new-site-dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import sites from '@/routes/sites';
import type { Site } from '@/types';

export default function SitesIndex({
    sites: siteList,
    canManage,
}: {
    sites: Site[];
    canManage: boolean;
}) {
    return (
        <>
            <Head title="Sites" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Sites
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Practice locations — rooms and storage locations
                            nest under each one.
                        </p>
                    </div>
                    {canManage && (
                        <div className="no-print">
                            <NewSiteDialog />
                        </div>
                    )}
                </div>

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-muted/40">
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>City</TableHead>
                                <TableHead className="text-right">
                                    Rooms
                                </TableHead>
                                <TableHead className="text-right">
                                    Storage locations
                                </TableHead>
                                <TableHead className="text-right">
                                    Devices
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {siteList.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {canManage
                                            ? 'No sites have been added yet — add one to get started.'
                                            : 'No sites have been added yet.'}
                                    </TableCell>
                                </TableRow>
                            )}
                            {siteList.map((site) => (
                                <TableRow key={site.id} className="h-10">
                                    <TableCell>
                                        <Link
                                            href={sites.show(site.id).url}
                                            className="flex items-center gap-2 hover:underline"
                                        >
                                            <Building2 className="size-4 text-muted-foreground" />
                                            {site.name}
                                            {site.is_primary && (
                                                <span className="rounded-md bg-primary/10 px-1.5 py-0.5 text-xs font-medium text-primary">
                                                    Primary
                                                </span>
                                            )}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {site.city ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {site.rooms_count ?? 0}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {site.storage_locations_count ?? 0}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {site.devices_count ?? 0}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

SitesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sites', href: sites.index() },
    ],
};
