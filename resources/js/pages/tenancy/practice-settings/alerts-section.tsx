import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label as FieldLabel } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { tenantRoleLabel } from '@/lib/status';
import practiceSettings from '@/routes/practice-settings';
import type { AlertSettings, DigestSubscription } from '@/types';

function NearExpiryPreview({ days }: { days: string }) {
    const [impact, setImpact] = useState<{
        appearing: number;
        disappearing: number;
    } | null>(null);

    useEffect(() => {
        const value = Number(days);

        const handle = setTimeout(() => {
            if (!value || value < 1) {
                setImpact(null);

                return;
            }

            fetch(practiceSettings.alerts.previewNearExpiry().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((row) => row.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? '',
                    ),
                },
                body: JSON.stringify({ near_expiry_days: value }),
            })
                .then((response) => (response.ok ? response.json() : null))
                .then(setImpact)
                .catch(() => setImpact(null));
        }, 400);

        return () => clearTimeout(handle);
    }, [days]);

    if (!impact || (impact.appearing === 0 && impact.disappearing === 0)) {
        return null;
    }

    return (
        <p className="text-xs text-muted-foreground">
            At this window: {impact.appearing} new near-expiry alert
            {impact.appearing === 1 ? '' : 's'} would appear,{' '}
            {impact.disappearing} would disappear. Resolved alerts stay
            untouched either way.
        </p>
    );
}

export function AlertsSection({
    alertSettings,
    digestSubscriptions,
}: {
    alertSettings: AlertSettings;
    digestSubscriptions: DigestSubscription[];
}) {
    const form = useForm({
        near_expiry_days: String(alertSettings.near_expiry_days),
        default_low_stock_threshold: String(
            alertSettings.default_low_stock_threshold,
        ),
        control_test_frequency_days:
            alertSettings.control_test_frequency_days === null
                ? ''
                : String(alertSettings.control_test_frequency_days),
        control_test_overdue_tolerance_days:
            alertSettings.control_test_overdue_tolerance_days === null
                ? ''
                : String(alertSettings.control_test_overdue_tolerance_days),
        failed_cycle_escalation_hours: String(
            alertSettings.failed_cycle_escalation_hours,
        ),
        digests_enabled: alertSettings.digests_enabled,
        reason: '',
    });

    const digestForm = useForm<{
        subscriptions: DigestSubscription[];
        reason: string;
    }>({
        subscriptions: digestSubscriptions,
        reason: '',
    });

    function submit() {
        form.patch(practiceSettings.alerts.update().url, {
            preserveScroll: true,
            onSuccess: () => form.setData('reason', ''),
        });
    }

    function submitDigests() {
        digestForm.put(practiceSettings.digestSubscriptions.update().url, {
            preserveScroll: true,
            onSuccess: () => digestForm.setData('reason', ''),
        });
    }

    function updateSubscription(
        role: string,
        patch: Partial<DigestSubscription>,
    ) {
        digestForm.setData(
            'subscriptions',
            digestForm.data.subscriptions.map((subscription) =>
                subscription.role === role
                    ? { ...subscription, ...patch }
                    : subscription,
            ),
        );
    }

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">
                        Alert thresholds
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="near-expiry-days">
                                Near-expiry window (days)
                            </FieldLabel>
                            <Input
                                id="near-expiry-days"
                                type="number"
                                min={1}
                                value={form.data.near_expiry_days}
                                onChange={(e) =>
                                    form.setData(
                                        'near_expiry_days',
                                        e.target.value,
                                    )
                                }
                            />
                            <NearExpiryPreview
                                days={form.data.near_expiry_days}
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="default-low-stock">
                                Default low-stock threshold
                            </FieldLabel>
                            <Input
                                id="default-low-stock"
                                type="number"
                                min={0}
                                value={form.data.default_low_stock_threshold}
                                onChange={(e) =>
                                    form.setData(
                                        'default_low_stock_threshold',
                                        e.target.value,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Applies to new products only — each
                                product's own threshold can still be set
                                individually.
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="control-test-frequency">
                                Control test frequency (days)
                            </FieldLabel>
                            <Input
                                id="control-test-frequency"
                                type="number"
                                min={1}
                                value={form.data.control_test_frequency_days}
                                onChange={(e) =>
                                    form.setData(
                                        'control_test_frequency_days',
                                        e.target.value,
                                    )
                                }
                                placeholder="Not set"
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="control-test-tolerance">
                                Overdue tolerance (days)
                            </FieldLabel>
                            <Input
                                id="control-test-tolerance"
                                type="number"
                                min={0}
                                value={
                                    form.data
                                        .control_test_overdue_tolerance_days
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'control_test_overdue_tolerance_days',
                                        e.target.value,
                                    )
                                }
                                placeholder="Not set"
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="failed-cycle-escalation">
                                Failed-cycle escalation (hours)
                            </FieldLabel>
                            <Input
                                id="failed-cycle-escalation"
                                type="number"
                                min={1}
                                value={
                                    form.data.failed_cycle_escalation_hours
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'failed_cycle_escalation_hours',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.digests_enabled}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'digests_enabled',
                                    checked === true,
                                )
                            }
                        />
                        Send email digests
                    </label>

                    <div className="grid gap-2">
                        <FieldLabel htmlFor="alert-settings-reason">
                            Reason
                        </FieldLabel>
                        <Textarea
                            id="alert-settings-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            placeholder="Why are these thresholds changing?"
                        />
                        {form.errors.reason && (
                            <p className="text-sm text-destructive">
                                {form.errors.reason}
                            </p>
                        )}
                    </div>

                    <div>
                        <Button
                            onClick={submit}
                            disabled={
                                form.processing || !form.data.reason.trim()
                            }
                        >
                            Save thresholds
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">
                        Digest subscriptions
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <p className="text-sm text-muted-foreground">
                        Sent via Resend to every active user currently
                        holding the role, not a stored address list — it
                        stays correct as the team changes. Turned off
                        entirely above disables every digest for this
                        practice regardless of these subscriptions.
                    </p>

                    <div className="overflow-hidden rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Frequency</TableHead>
                                    <TableHead>Enabled</TableHead>
                                    <TableHead>Last sent</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {digestForm.data.subscriptions.map(
                                    (subscription) => (
                                        <TableRow
                                            key={subscription.role}
                                            className="h-10"
                                        >
                                            <TableCell>
                                                {tenantRoleLabel[
                                                    subscription.role
                                                ] ?? subscription.role}
                                            </TableCell>
                                            <TableCell>
                                                <Select
                                                    value={
                                                        subscription.frequency
                                                    }
                                                    onValueChange={(value) =>
                                                        updateSubscription(
                                                            subscription.role,
                                                            {
                                                                frequency:
                                                                    value as DigestSubscription['frequency'],
                                                            },
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="h-8 w-32">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="daily">
                                                            Daily
                                                        </SelectItem>
                                                        <SelectItem value="weekly">
                                                            Weekly
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </TableCell>
                                            <TableCell>
                                                <Checkbox
                                                    checked={
                                                        subscription.enabled
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        updateSubscription(
                                                            subscription.role,
                                                            {
                                                                enabled:
                                                                    checked ===
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                    aria-label={`${subscription.role} digest enabled`}
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {subscription.last_sent_at
                                                    ? new Date(
                                                          subscription.last_sent_at,
                                                      ).toLocaleString()
                                                    : 'Never'}
                                            </TableCell>
                                        </TableRow>
                                    ),
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="grid gap-2">
                        <FieldLabel htmlFor="digest-subscriptions-reason">
                            Reason
                        </FieldLabel>
                        <Textarea
                            id="digest-subscriptions-reason"
                            value={digestForm.data.reason}
                            onChange={(e) =>
                                digestForm.setData('reason', e.target.value)
                            }
                        />
                        {digestForm.errors.reason && (
                            <p className="text-sm text-destructive">
                                {digestForm.errors.reason}
                            </p>
                        )}
                    </div>

                    <div>
                        <Button
                            onClick={submitDigests}
                            disabled={
                                digestForm.processing ||
                                !digestForm.data.reason.trim()
                            }
                        >
                            Save digest subscriptions
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
