import { useForm } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import { sheetLayoutLabel } from '@/lib/status';
import practiceSettings from '@/routes/practice-settings';
import type { LabelFormatVersion } from '@/types';

const DEFAULTS: Pick<
    LabelFormatVersion,
    'primary_code' | 'sheet_layout' | 'show_batch_number' | 'show_product_name'
> = {
    primary_code: 'datamatrix',
    sheet_layout: 'a4_24',
    show_batch_number: false,
    show_product_name: false,
};

export function LabelFormatSection({
    labelFormat,
    canManage,
}: {
    labelFormat: LabelFormatVersion | null;
    canManage: boolean;
}) {
    const current = labelFormat ?? DEFAULTS;
    const form = useForm({
        primary_code: current.primary_code,
        sheet_layout: current.sheet_layout,
        show_batch_number: current.show_batch_number,
        show_product_name: current.show_product_name,
        reason: '',
    });
    const [confirmOpen, setConfirmOpen] = useState(false);

    function testPrint() {
        const params = new URLSearchParams({
            primary_code: form.data.primary_code,
            sheet_layout: form.data.sheet_layout,
            show_batch_number: form.data.show_batch_number ? '1' : '0',
            show_product_name: form.data.show_product_name ? '1' : '0',
        });
        window.open(
            `${practiceSettings.labelFormat.sample().url}?${params.toString()}`,
            '_blank',
        );
    }

    function submit() {
        form.post(practiceSettings.labelFormat.update().url, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                form.setData('reason', '');
            },
        });
    }

    const hasChanges =
        form.data.primary_code !== current.primary_code ||
        form.data.sheet_layout !== current.sheet_layout ||
        form.data.show_batch_number !== current.show_batch_number ||
        form.data.show_product_name !== current.show_product_name;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">Label format</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <p className="text-sm text-muted-foreground">
                    QR and DataMatrix always both print on every label —
                    this only controls which is visually larger, the sheet
                    layout, and which optional fields also appear. Changing
                    it applies to labels printed{' '}
                    <strong>from now on only</strong>; every already-printed
                    label keeps the payload and appearance it was printed
                    with.
                </p>

                {!canManage && (
                    <p className="text-sm text-muted-foreground italic">
                        Only the practice owner can change the label
                        format.
                    </p>
                )}

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="label-format-primary-code">
                            Primary code
                        </FieldLabel>
                        <Select
                            value={form.data.primary_code}
                            onValueChange={(value) =>
                                form.setData(
                                    'primary_code',
                                    value as LabelFormatVersion['primary_code'],
                                )
                            }
                            disabled={!canManage}
                        >
                            <SelectTrigger
                                id="label-format-primary-code"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="datamatrix">
                                    DataMatrix
                                </SelectItem>
                                <SelectItem value="qr">QR</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="label-format-sheet-layout">
                            Sheet layout
                        </FieldLabel>
                        <Select
                            value={form.data.sheet_layout}
                            onValueChange={(value) =>
                                form.setData(
                                    'sheet_layout',
                                    value as LabelFormatVersion['sheet_layout'],
                                )
                            }
                            disabled={!canManage}
                        >
                            <SelectTrigger
                                id="label-format-sheet-layout"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(sheetLayoutLabel).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="flex flex-col gap-3">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.show_batch_number}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'show_batch_number',
                                    checked === true,
                                )
                            }
                            disabled={!canManage}
                        />
                        Show batch number (in addition to the mandatory
                        fields)
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.show_product_name}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'show_product_name',
                                    checked === true,
                                )
                            }
                            disabled={!canManage}
                        />
                        Show product name (in addition to the mandatory
                        fields)
                    </label>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" onClick={testPrint}>
                        <Printer className="size-4" />
                        Test print a sample sheet
                    </Button>

                    {canManage && (
                        <Dialog
                            open={confirmOpen}
                            onOpenChange={setConfirmOpen}
                        >
                            <DialogTrigger asChild>
                                <Button disabled={!hasChanges}>
                                    Save changes
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Save the new label format?
                                    </DialogTitle>
                                    <DialogDescription>
                                        New labels printed from now on use
                                        this format. Every label printed
                                        before today keeps the exact
                                        appearance it was printed with —
                                        nothing is re-rendered.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="label-format-reason">
                                        Reason
                                    </FieldLabel>
                                    <Textarea
                                        id="label-format-reason"
                                        value={form.data.reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'reason',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Why is the format changing?"
                                    />
                                    {form.errors.reason && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.reason}
                                        </p>
                                    )}
                                </div>

                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        onClick={() => setConfirmOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        onClick={submit}
                                        disabled={
                                            form.processing ||
                                            !form.data.reason.trim()
                                        }
                                    >
                                        Save label format
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {labelFormat && (
                    <p className="text-xs text-muted-foreground">
                        Last changed{' '}
                        {new Date(labelFormat.effective_from).toLocaleString()}
                        {labelFormat.updated_by &&
                            ` by ${labelFormat.updated_by}`}
                        {' — '}
                        {labelFormat.reason}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
