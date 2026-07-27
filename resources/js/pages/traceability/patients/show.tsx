import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import labels from '@/routes/labels';
import patients from '@/routes/patients';
import type { Patient, PatientUsageEntry } from '@/types';

function ArchivePatientDialog({ patient }: { patient: Patient }) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button size="sm" variant="destructive">
                    Archive patient
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Archive {patient.reference}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        This pseudonym will no longer appear when linking a
                        new label to a procedure. Its past procedure history
                        stays exactly as recorded — nothing here is ever
                        deleted.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() =>
                            router.delete(patients.destroy(patient.id).url)
                        }
                    >
                        Archive patient
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export default function PatientShow({
    patient,
    usages,
    canManage,
}: {
    patient: Patient;
    usages: PatientUsageEntry[];
    canManage: boolean;
}) {
    return (
        <>
            <Head title={patient.reference} />

            <div className="flex h-full flex-col gap-4 p-4">
                <div>
                    <Link
                        href={patients.index().url}
                        className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" />
                        Back to patients
                    </Link>
                    <div className="flex items-center justify-between">
                        <h1 className="text-lg font-semibold tracking-tight">
                            {patient.reference}
                        </h1>
                        {canManage && (
                            <ArchivePatientDialog patient={patient} />
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">
                            Procedure history
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {usages.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No procedures recorded yet for this patient
                                — link a scanned label to a procedure to see
                                it here.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Procedure</TableHead>
                                            <TableHead>
                                                Practitioner
                                            </TableHead>
                                            <TableHead className="w-44">
                                                Used
                                            </TableHead>
                                            <TableHead>Notes</TableHead>
                                            <TableHead className="w-24" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {usages.map((usage) => (
                                            <TableRow
                                                key={usage.id}
                                                className="h-10"
                                            >
                                                <TableCell>
                                                    {usage.procedure}
                                                </TableCell>
                                                <TableCell>
                                                    {usage.practitioner_name}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {formatDateTime(
                                                        usage.used_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {usage.notes ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <Link
                                                        href={
                                                            labels.show(
                                                                usage.label_id,
                                                            ).url
                                                        }
                                                        className="text-sm hover:underline"
                                                    >
                                                        View label
                                                    </Link>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PatientShow.layout = (props: { patient: Patient }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Patients', href: patients.index() },
        {
            title: props.patient.reference,
            href: patients.show(props.patient.id),
        },
    ],
});
