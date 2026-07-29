import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cycleReleaseDecision, cycleStatus } from '@/lib/status';
import cycles from '@/routes/cycles';
import type { TodayCycle } from '@/types';

export function TodayGlanceSkeleton() {
    return <Skeleton className="h-48 rounded-lg" />;
}

export function TodayGlance({
    rows,
    error,
}: {
    rows: TodayCycle[];
    error: boolean;
}) {
    if (error) {
        return (
            <div className="flex items-center gap-3 rounded-lg border p-4">
                <TriangleAlert className="size-5 shrink-0 text-status-danger-fg" />
                <p className="text-sm text-muted-foreground">
                    Couldn't load today's cycles.
                </p>
            </div>
        );
    }

    if (rows.length === 0) {
        return (
            <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                No cycles run today yet.
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border">
            <Table>
                <TableHeader className="bg-muted/40">
                    <TableRow>
                        <TableHead>Device</TableHead>
                        <TableHead>Cycle</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Release decision</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.id} className="h-11">
                            <TableCell>{row.device_name}</TableCell>
                            <TableCell>
                                <Link
                                    href={cycles.show(row.id).url}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    #{row.cycle_number}
                                </Link>
                            </TableCell>
                            <TableCell>
                                <StatusBadge status={cycleStatus[row.status]} />
                            </TableCell>
                            <TableCell>
                                {row.release_decision ? (
                                    <StatusBadge
                                        status={
                                            cycleReleaseDecision[
                                                row.release_decision
                                            ]
                                        }
                                    />
                                ) : (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
