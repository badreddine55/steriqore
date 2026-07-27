import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, PackageX } from 'lucide-react';
import { Mono } from '@/components/mono';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import products from '@/routes/products';
import quarantine from '@/routes/quarantine';
import type { CursorPaginated, QuarantinedBatch } from '@/types';

export default function QuarantineIndex({
    batches: batchesPage,
}: {
    batches: CursorPaginated<QuarantinedBatch>;
}) {
    function goTo(url: string | null) {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['batches'],
        });
    }

    return (
        <>
            <Head title="Quarantine" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Quarantine
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Batches taken out of use by a non-conformity — who,
                        why, and when. There is no un-quarantine here; that
                        needs its own deliberate, verified follow-up.
                    </p>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-muted/40">
                            <TableRow>
                                <TableHead>Batch</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead className="w-24 text-right">
                                    On hand
                                </TableHead>
                                <TableHead className="w-36">
                                    Quarantined
                                </TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {batchesPage.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-1">
                                            <PackageX className="size-5 text-muted-foreground" />
                                            No batches are quarantined right
                                            now.
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                            {batchesPage.data.map((batch) => (
                                <TableRow key={batch.id} className="h-10">
                                    <TableCell>
                                        <Link
                                            href={
                                                products.show(
                                                    batch.product_id,
                                                ).url
                                            }
                                            className="hover:underline"
                                        >
                                            <Mono>{batch.batch_number}</Mono>
                                        </Link>
                                    </TableCell>
                                    <TableCell>{batch.product_name}</TableCell>
                                    <TableCell>
                                        {batch.supplier_name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Mono>{batch.qty_on_hand}</Mono>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        <Mono className="text-xs">
                                            {batch.quarantined_at
                                                ? formatDateTime(
                                                      batch.quarantined_at,
                                                  )
                                                : '—'}
                                        </Mono>
                                    </TableCell>
                                    <TableCell>
                                        {batch.reason ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {batch.quarantined_by ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="no-print flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!batchesPage.prev_page_url}
                        onClick={() => goTo(batchesPage.prev_page_url)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!batchesPage.next_page_url}
                        onClick={() => goTo(batchesPage.next_page_url)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

QuarantineIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Quarantine', href: quarantine.index() },
    ],
};
