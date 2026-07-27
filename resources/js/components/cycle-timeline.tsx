import { Check, CircleDashed, PlayCircle, X } from 'lucide-react';
import { cn } from '@/lib/utils';

type Station =
    'draft' | 'running' | 'completed' | 'awaiting_release' | 'terminal';

const STATIONS: { key: Station; label: string }[] = [
    { key: 'draft', label: 'Draft' },
    { key: 'running', label: 'Running' },
    { key: 'completed', label: 'Completed' },
    { key: 'awaiting_release', label: 'Awaiting release' },
    { key: 'terminal', label: 'Released / Rejected' },
];

const STATUS_TO_INDEX: Record<string, number> = {
    draft: 0,
    running: 1,
    completed: 2,
    awaiting_release: 3,
    released: 4,
    rejected: 4,
};

/**
 * The signature element (S7 design direction): the one place to spend
 * visual effort, kept quiet everywhere else. A horizontal state track —
 * draft -> running -> completed -> awaiting_release -> released/rejected —
 * with the terminal station taking on the compliance outcome's tone once
 * reached, never before (an undecided cycle shows a neutral placeholder,
 * not a color that would wrongly imply a decision was made).
 */
export function CycleTimeline({ status }: { status: string }) {
    const currentIndex = STATUS_TO_INDEX[status] ?? 0;
    const isRejected = status === 'rejected';
    const isReleased = status === 'released';

    return (
        <ol className="flex items-start" aria-label="Cycle progress">
            {STATIONS.map((station, index) => {
                const isTerminal = station.key === 'terminal';
                const reached = index <= currentIndex;
                const isCurrent = index === currentIndex;

                let circleClasses =
                    'border-border bg-background text-muted-foreground';
                let Icon = CircleDashed;

                if (isTerminal && reached) {
                    if (isRejected) {
                        circleClasses =
                            'border-status-danger-fg bg-status-danger-bg text-status-danger-fg';
                        Icon = X;
                    } else if (isReleased) {
                        circleClasses =
                            'border-status-success-fg bg-status-success-bg text-status-success-fg';
                        Icon = Check;
                    }
                } else if (reached && isCurrent) {
                    circleClasses = 'border-primary bg-primary/10 text-primary';
                    Icon = PlayCircle;
                } else if (reached) {
                    circleClasses =
                        'border-primary bg-primary text-primary-foreground';
                    Icon = Check;
                }

                return (
                    <li
                        key={station.key}
                        className="flex flex-1 flex-col items-center last:flex-none"
                    >
                        <div className="flex w-full items-center">
                            {index > 0 && (
                                <div
                                    className={cn(
                                        'h-0.5 flex-1',
                                        index <= currentIndex
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                    aria-hidden="true"
                                />
                            )}
                            <div
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-full border-2',
                                    circleClasses,
                                )}
                                aria-current={isCurrent ? 'step' : undefined}
                            >
                                <Icon className="size-4" aria-hidden="true" />
                            </div>
                        </div>
                        <span
                            className={cn(
                                'mt-2 max-w-24 text-center text-xs',
                                reached
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {station.label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
