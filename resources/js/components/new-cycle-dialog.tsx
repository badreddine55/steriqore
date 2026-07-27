import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import cycles from '@/routes/cycles';
import type { DeviceProgram, Option } from '@/types';

export function NewCycleDialog({
    devices,
    devicePrograms,
}: {
    devices: Option[];
    devicePrograms: DeviceProgram[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        device_id: '',
        device_program_id: '',
        notes: '',
    });

    const programsForDevice = devicePrograms.filter(
        (program) => program.device_id === form.data.device_id,
    );

    function submit() {
        form.post(cycles.store().url, {
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
                <Button size="sm">
                    <Plus className="size-4" />
                    New cycle
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Start a new cycle</DialogTitle>
                    <DialogDescription>
                        Pick the autoclave running this load. You'll add the
                        load items next.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="device_id">Device</Label>
                        <Select
                            value={form.data.device_id}
                            onValueChange={(value) => {
                                form.setData('device_id', value);
                                form.setData('device_program_id', '');
                            }}
                        >
                            <SelectTrigger id="device_id" className="w-full">
                                <SelectValue placeholder="Select a device…" />
                            </SelectTrigger>
                            <SelectContent>
                                {devices.map((device) => (
                                    <SelectItem
                                        key={device.id}
                                        value={device.id}
                                    >
                                        {device.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.device_id && (
                            <p className="text-sm text-destructive">
                                {form.errors.device_id}
                            </p>
                        )}
                    </div>

                    {form.data.device_id && programsForDevice.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="device_program_id">
                                Program (optional)
                            </Label>
                            <Select
                                value={form.data.device_program_id}
                                onValueChange={(value) =>
                                    form.setData('device_program_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="device_program_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="No specific program" />
                                </SelectTrigger>
                                <SelectContent>
                                    {programsForDevice.map((program) => (
                                        <SelectItem
                                            key={program.id}
                                            value={program.id}
                                        >
                                            {program.name} (
                                            {program.target_temperature_celsius}
                                            °C, {program.plateau_minutes} min)
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes (optional)</Label>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            placeholder="Anything worth noting about this run…"
                        />
                        {form.errors.notes && (
                            <p className="text-sm text-destructive">
                                {form.errors.notes}
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
                        disabled={form.processing || !form.data.device_id}
                    >
                        Create cycle
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
