import { Head, router } from '@inertiajs/react';
import { Download, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatBytes, formatDateTime } from '@/lib/format';
import { dataExportStatus } from '@/lib/status';
import { dashboard } from '@/routes';
import dataExports from '@/routes/data-exports';
import type { CursorPaginated, DataExportRequest } from '@/types';

const POLL_INTERVAL_MS = 4000;

function csrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function ManifestDialog({ request }: { request: DataExportRequest }) {
    const [open, setOpen] = useState(false);
    const manifest = Object.entries(request.table_manifest ?? {}).sort(
        ([a], [b]) => a.localeCompare(b),
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
                View contents
            </Button>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>What&apos;s inside this export</DialogTitle>
                </DialogHeader>

                <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">Attached files</dt>
                    <dd className="text-right tabular-nums">
                        {request.file_count ?? 0}
                    </dd>
                    <dt className="text-muted-foreground">Total size</dt>
                    <dd className="text-right tabular-nums">
                        {formatBytes(request.size_bytes)}
                    </dd>
                </dl>

                <div className="max-h-72 overflow-y-auto rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 bg-muted/40">
                            <TableRow>
                                <TableHead>Table</TableHead>
                                <TableHead className="text-right">
                                    Rows
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {manifest.map(([table, count]) => (
                                <TableRow key={table}>
                                    <TableCell className="font-mono text-xs">
                                        {table}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {count}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function DownloadButton({ request }: { request: DataExportRequest }) {
    const [isDownloading, setIsDownloading] = useState(false);

    async function handleDownload() {
        setIsDownloading(true);

        try {
            const response = await fetch(
                dataExports.download(request.id).url,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': csrfToken(),
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            const data: { download_url: string } = await response.json();
            window.open(data.download_url, '_blank', 'noopener,noreferrer');
        } finally {
            setIsDownloading(false);
        }
    }

    return (
        <Button size="sm" onClick={handleDownload} disabled={isDownloading}>
            <Download className="size-3.5" />
            Download
        </Button>
    );
}

function RequestExportDialog({
    disabledReason,
}: {
    disabledReason: string | null;
}) {
    function submit() {
        router.post(dataExports.store().url, {}, { preserveScroll: true });
    }

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button disabled={disabledReason !== null}>
                    Request export
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Request a full data export?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        This generates a complete copy of every record and
                        attached file for this practice, including
                        pseudonymized patient references. Every owner will be
                        emailed when this export is requested, and the
                        download link expires 30 minutes after you generate
                        it.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={submit}>
                        Request export
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export default function DataExportsIndex({
    exports: exportsPage,
    hasRunningExport,
    weeklyLimit,
}: {
    exports: CursorPaginated<DataExportRequest>;
    hasRunningExport: boolean;
    weeklyLimit: number;
}) {
    useEffect(() => {
        if (!hasRunningExport) {
            return;
        }

        const interval = setInterval(() => {
            router.reload({ only: ['exports', 'hasRunningExport'] });
        }, POLL_INTERVAL_MS);

        return () => clearInterval(interval);
    }, [hasRunningExport]);

    const disabledReason = hasRunningExport
        ? 'An export is already in progress. Wait for it to finish before requesting another.'
        : null;

    return (
        <>
            <Head title="Data export" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Data export
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Export every record and attached file this practice
                        has stored — for GDPR data portability, or if you're
                        leaving SteriQore. Up to {weeklyLimit} requests per
                        week.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Request an export</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        <p className="text-sm text-muted-foreground">
                            The archive contains every table this practice
                            owns and every attached file, in readable
                            formats (JSON records, original files), zipped
                            into a single download. Generating it usually
                            takes a few minutes depending on how much data
                            there is.
                        </p>
                        <div>
                            <RequestExportDialog
                                disabledReason={disabledReason}
                            />
                            {disabledReason && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {disabledReason}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-muted/40">
                            <TableRow>
                                <TableHead>Requested</TableHead>
                                <TableHead>Requested by</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Size</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead className="w-56" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {exportsPage.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No exports requested yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {exportsPage.data.map((request) => {
                                const status =
                                    dataExportStatus[request.status];

                                return (
                                    <TableRow key={request.id} className="h-12">
                                        <TableCell>
                                            {formatDateTime(
                                                request.requested_at,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {request.requested_by_name}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={status} />
                                            {request.status === 'failed' &&
                                                request.error && (
                                                    <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                                        {request.error}
                                                    </p>
                                                )}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {formatBytes(request.size_bytes)}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateTime(
                                                request.expires_at,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {request.status ===
                                                    'completed' && (
                                                    <>
                                                        <ManifestDialog
                                                            request={request}
                                                        />
                                                        <DownloadButton
                                                            request={request}
                                                        />
                                                    </>
                                                )}
                                                {request.status ===
                                                    'failed' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={
                                                            hasRunningExport
                                                        }
                                                        onClick={() =>
                                                            router.post(
                                                                dataExports.store()
                                                                    .url,
                                                                {},
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <RotateCcw className="size-3.5" />
                                                        Request again
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>

                <p className="text-xs text-muted-foreground">
                    Download links are generated on demand and are valid for
                    30 minutes. The archive contains pseudonymous patient
                    references — treat it as sensitive.
                </p>
            </div>
        </>
    );
}

DataExportsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data export', href: dataExports.index() },
    ],
};
