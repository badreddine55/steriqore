import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime } from '@/lib/format';
import labels from '@/routes/labels';
import type { RecentProcedure } from '@/types';

export function RecentProceduresSkeleton() {
    return <Skeleton className="h-32 rounded-lg" />;
}

/** A practitioner's own recent procedures (UI-30 role composition) — never anyone else's. */
export function RecentProcedures({
    procedures,
    error,
}: {
    procedures: RecentProcedure[];
    error: boolean;
}) {
    if (error) {
        return (
            <div className="flex items-center gap-3 rounded-lg border p-4">
                <TriangleAlert className="size-5 shrink-0 text-status-danger-fg" />
                <p className="text-sm text-muted-foreground">
                    Couldn't load your recent procedures.
                </p>
            </div>
        );
    }

    if (procedures.length === 0) {
        return (
            <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                No procedures recorded yet.
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-2">
            {procedures.map((procedure) => (
                <li
                    key={procedure.label_id}
                    className="flex items-center justify-between rounded-lg border p-3"
                >
                    <Link
                        href={labels.show(procedure.label_id).url}
                        className="text-sm font-medium underline-offset-4 hover:underline"
                    >
                        {procedure.procedure}
                    </Link>
                    <span className="text-xs text-muted-foreground">
                        {formatDateTime(procedure.used_at)}
                    </span>
                </li>
            ))}
        </ul>
    );
}
