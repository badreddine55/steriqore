import { Head, router, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    AttentionQueue,
    AttentionQueueSkeleton,
} from '@/components/dashboard/attention-queue';
import {
    OnboardingChecklist,
    OnboardingChecklistSkeleton,
} from '@/components/dashboard/onboarding-checklist';
import {
    RecentProcedures,
    RecentProceduresSkeleton,
} from '@/components/dashboard/recent-procedures';
import {
    TodayGlance,
    TodayGlanceSkeleton,
} from '@/components/dashboard/today-glance';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { DashboardData } from '@/types';

/**
 * UI-30: "what needs me today," not an analytics page — replaces the
 * starter-kit placeholder cards. The Refresh button is the one client-
 * triggered reload this page has (no filters to drive one otherwise); it
 * also lets someone see past BuildDashboardAction's 60s cache TTL sooner
 * after acting on something. Each section shows its own skeleton while a
 * reload is in flight and its own error state if its data failed to load
 * server-side (see BuildDashboardAction) — one broken query never blanks
 * the rest of the page.
 */
export default function Dashboard({
    dashboard: data,
}: {
    dashboard: DashboardData;
}) {
    const [isLoading, setIsLoading] = useState(false);
    const { auth } = usePage().props;

    useEffect(() => {
        const removeStart = router.on('start', () => setIsLoading(true));
        const removeFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    const showRecentProcedures =
        auth.role === 'practitioner' || data.my_recent_procedures.length > 0;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            What needs you today.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.reload()}
                        disabled={isLoading}
                    >
                        <RefreshCw
                            className={
                                isLoading ? 'size-3.5 animate-spin' : 'size-3.5'
                            }
                        />
                        Refresh
                    </Button>
                </div>

                {data.is_new_practice ? (
                    isLoading ? (
                        <OnboardingChecklistSkeleton />
                    ) : (
                        <OnboardingChecklist steps={data.onboarding_steps} />
                    )
                ) : (
                    <>
                        <section className="flex flex-col gap-2">
                            <h2 className="sr-only">Attention queue</h2>
                            {isLoading ? (
                                <AttentionQueueSkeleton />
                            ) : (
                                <AttentionQueue
                                    cards={data.attention_cards}
                                    role={auth.role}
                                />
                            )}
                        </section>

                        <section className="flex flex-col gap-2">
                            <h2 className="text-sm font-semibold text-muted-foreground">
                                Today at a glance
                            </h2>
                            {isLoading ? (
                                <TodayGlanceSkeleton />
                            ) : (
                                <TodayGlance
                                    rows={data.today_cycles}
                                    error={data.today_cycles_error}
                                />
                            )}
                        </section>

                        {showRecentProcedures && (
                            <section className="flex flex-col gap-2">
                                <h2 className="text-sm font-semibold text-muted-foreground">
                                    Your recent procedures
                                </h2>
                                {isLoading ? (
                                    <RecentProceduresSkeleton />
                                ) : (
                                    <RecentProcedures
                                        procedures={data.my_recent_procedures}
                                        error={data.my_recent_procedures_error}
                                    />
                                )}
                            </section>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
