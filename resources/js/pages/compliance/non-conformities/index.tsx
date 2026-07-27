import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Mono } from '@/components/mono';
import { StatusBadge } from '@/components/status-badge';
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
import { Label as FieldLabel } from '@/components/ui/label';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { nonConformityState } from '@/lib/status';
import { dashboard } from '@/routes';
import cycles from '@/routes/cycles';
import labels from '@/routes/labels';
import nonConformities from '@/routes/non-conformities';
import type {
    CursorPaginated,
    NonConformity,
    NonConformityFilters,
} from '@/types';

const SUBJECT_TYPE_OPTIONS = ['Cycle', 'Label'];

function ResolveDialog({ nonConformity }: { nonConformity: NonConformity }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ resolution: '' });

    function submit() {
        form.post(nonConformities.resolve(nonConformity.id).url, {
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
                    Resolve
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Resolve this non-conformity</DialogTitle>
                    <DialogDescription>
                        This closes the record with a note — it does not
                        un-recall any label or un-quarantine any batch.
                        That needs its own deliberate follow-up once
                        someone has actually verified it&apos;s safe.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    <FieldLabel htmlFor="resolution">Resolution</FieldLabel>
                    <Textarea
                        id="resolution"
                        value={form.data.resolution}
                        onChange={(e) =>
                            form.setData('resolution', e.target.value)
                        }
                        placeholder="What was done to address this?"
                    />
                    {form.errors.resolution && (
                        <p className="text-sm text-destructive">
                            {form.errors.resolution}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={
                            form.processing || !form.data.resolution.trim()
                        }
                    >
                        Resolve
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function subjectHref(nonConformity: NonConformity): string {
    return nonConformity.subject_type === 'Cycle'
        ? cycles.show(nonConformity.subject_id).url
        : labels.show(nonConformity.subject_id).url;
}

export default function NonConformitiesIndex({
    nonConformities: nonConformitiesPage,
    canManage,
    filters,
}: {
    nonConformities: CursorPaginated<NonConformity>;
    canManage: boolean;
    filters: NonConformityFilters;
}) {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', () => setIsLoading(true));
        const removeFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    function applyFilters(next: Partial<NonConformityFilters>) {
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
            nonConformities.index().url,
            { filter: merged },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['nonConformities', 'filters'],
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
            only: ['nonConformities', 'filters'],
        });
    }

    const hasFilters = Object.keys(filters).length > 0;

    return (
        <>
            <Head title="Non-conformities" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        Non-conformities
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Every recall and quarantine, with why — corrections
                        here are audited records, never silent edits.
                    </p>
                </div>

                <div className="no-print flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.subject_type ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({ subject_type: value })
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-36">
                            <SelectValue placeholder="Subject" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All subjects</SelectItem>
                            {SUBJECT_TYPE_OPTIONS.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {type}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-muted/40">
                            <TableRow>
                                <TableHead className="w-24">
                                    Subject
                                </TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead className="w-44">
                                    Raised
                                </TableHead>
                                <TableHead className="w-32">
                                    Status
                                </TableHead>
                                {canManage && (
                                    <TableHead className="w-24" />
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, i) => (
                                    <TableRow
                                        key={`skeleton-${i}`}
                                        className="h-10"
                                    >
                                        {Array.from({
                                            length: canManage ? 5 : 4,
                                        }).map((__, j) => (
                                            <TableCell key={j}>
                                                <Skeleton className="h-4 w-full max-w-32" />
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            {!isLoading &&
                                nonConformitiesPage.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={canManage ? 5 : 4}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No non-conformities match this filter.'
                                                : 'No non-conformities have been raised — good sign.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                            {!isLoading &&
                                nonConformitiesPage.data.map((nc) => {
                                    const resolved = nc.resolved_at !== null;
                                    const status = resolved
                                        ? nonConformityState.resolved
                                        : nonConformityState.open;

                                    return (
                                        <TableRow
                                            key={nc.id}
                                            className="h-10"
                                        >
                                            <TableCell>
                                                <Link
                                                    href={subjectHref(nc)}
                                                    className="hover:underline"
                                                >
                                                    {nc.subject_type}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {nc.description}
                                                    {resolved && (
                                                        <p className="text-xs text-muted-foreground">
                                                            Resolved:{' '}
                                                            {nc.resolution}
                                                        </p>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Mono className="text-xs">
                                                    {formatDateTime(
                                                        nc.raised_at,
                                                    )}
                                                </Mono>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={status}
                                                />
                                            </TableCell>
                                            {canManage && (
                                                <TableCell>
                                                    {!resolved && (
                                                        <ResolveDialog
                                                            nonConformity={
                                                                nc
                                                            }
                                                        />
                                                    )}
                                                </TableCell>
                                            )}
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
                        disabled={!nonConformitiesPage.prev_page_url}
                        onClick={() =>
                            goTo(nonConformitiesPage.prev_page_url)
                        }
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!nonConformitiesPage.next_page_url}
                        onClick={() =>
                            goTo(nonConformitiesPage.next_page_url)
                        }
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </>
    );
}

NonConformitiesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Non-conformities', href: nonConformities.index() },
    ],
};
