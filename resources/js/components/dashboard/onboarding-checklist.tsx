import { Link } from '@inertiajs/react';
import { Circle, CircleCheck } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import cycles from '@/routes/cycles';
import devices from '@/routes/devices';
import siteRoutes from '@/routes/sites';
import type { OnboardingStep } from '@/types';

const HREF_BY_KEY: Record<string, () => string> = {
    add_site: () => siteRoutes.index().url,
    add_autoclave: () => devices.index().url,
    add_program: () => devices.index().url,
    run_first_cycle: () => cycles.index().url,
};

export function OnboardingChecklistSkeleton() {
    return <Skeleton className="h-64 rounded-lg" />;
}

/**
 * Shown instead of the attention queue for a tenant with no devices and no
 * cycles yet (UI-30) — eight empty attention cards would read as a broken
 * product to a trial user's first visit.
 */
export function OnboardingChecklist({ steps }: { steps: OnboardingStep[] }) {
    return (
        <div className="rounded-lg border p-6">
            <h2 className="text-base font-semibold">
                Get your practice set up
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                A few steps before your first sterilization cycle.
            </p>

            <ul className="mt-4 flex flex-col gap-2">
                {steps.map((step) => {
                    const href = HREF_BY_KEY[step.key]?.();
                    const Icon = step.done ? CircleCheck : Circle;

                    return (
                        <li
                            key={step.key}
                            className="flex items-center gap-3 rounded-md border p-3"
                        >
                            <Icon
                                className={
                                    step.done
                                        ? 'size-5 shrink-0 text-status-success-fg'
                                        : 'size-5 shrink-0 text-muted-foreground'
                                }
                                aria-hidden="true"
                            />
                            {step.done || !step.can_act || !href ? (
                                <span
                                    className={
                                        step.done
                                            ? 'text-sm line-through opacity-70'
                                            : 'text-sm'
                                    }
                                >
                                    {step.label}
                                </span>
                            ) : (
                                <Link
                                    href={href}
                                    className="text-sm font-medium underline-offset-4 hover:underline"
                                >
                                    {step.label}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
