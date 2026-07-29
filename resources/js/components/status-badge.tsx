import type { StatusMeta, StatusTone } from '@/lib/status';
import { cn } from '@/lib/utils';

export const TONE_CLASSES: Record<StatusTone, string> = {
    draft: 'bg-status-draft-bg text-status-draft-fg',
    pending: 'bg-status-pending-bg text-status-pending-fg',
    success: 'bg-status-success-bg text-status-success-fg',
    danger: 'bg-status-danger-bg text-status-danger-fg',
    contained: 'bg-status-contained-bg text-status-contained-fg',
    expired: 'bg-status-expired-bg text-status-expired-fg',
    // Not one of the six compliance-critical states — the neutral
    // institutional accent, tinted, rather than a 7th saturated hue.
    active: 'bg-primary/10 text-primary',
};

/**
 * Status is never conveyed by color alone — every badge always carries an
 * icon and a text label alongside its tone, so the distinction survives
 * greyscale printing (inspectors print these).
 */
export function StatusBadge({
    status,
    className,
}: {
    status: StatusMeta;
    className?: string;
}) {
    const Icon = status.icon;

    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                TONE_CLASSES[status.tone],
                className,
            )}
        >
            <Icon className="size-3" aria-hidden="true" />
            {status.label}
        </span>
    );
}
