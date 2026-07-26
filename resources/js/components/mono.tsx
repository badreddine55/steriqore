import { cn } from '@/lib/utils';

/**
 * Every identifier and quantity (cycle numbers, batch numbers, label
 * UUIDs, DLU dates, stock counts) renders through this so digits are
 * fixed-width and align vertically in a column — a mis-keyed digit
 * should be visible at a glance.
 */
export function Mono({
    className,
    children,
}: {
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <span className={cn('font-mono tabular-nums', className)}>
            {children}
        </span>
    );
}
