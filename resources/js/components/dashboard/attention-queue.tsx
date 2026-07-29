import { Link } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { TONE_CLASSES } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { StatusTone } from '@/lib/status';
import alertRoutes from '@/routes/alerts';
import cycles from '@/routes/cycles';
import labels from '@/routes/labels';
import nonConformityRoutes from '@/routes/non-conformities';
import type { AttentionCard } from '@/types';

/**
 * Zero is a success state (CLAUDE.md/UI-30) — every card renders a check
 * and a plain sentence, not a grey box with "0" in it. `tone` only applies
 * once there's actually something to act on; severity is always icon + text
 * + color together, never color alone.
 */
const CARD_META: Record<
    string,
    { zeroLabel: string; tone: StatusTone; href: () => string }
> = {
    cycles_awaiting_release: {
        zeroLabel: 'Nothing awaiting release',
        tone: 'pending',
        href: () =>
            cycles.index({ query: { filter: { status: 'awaiting_release' } } })
                .url,
    },
    cycles_rejected_no_non_conformity: {
        zeroLabel: 'No rejected cycles missing a non-conformity',
        tone: 'danger',
        href: () =>
            cycles.index({
                query: {
                    filter: { status: 'rejected', missing_non_conformity: true },
                },
            }).url,
    },
    open_non_conformities: {
        zeroLabel: 'No open non-conformities or recalls',
        tone: 'contained',
        href: () =>
            nonConformityRoutes.index({ query: { filter: { resolved: false } } })
                .url,
    },
    batches_expired_in_stock: {
        zeroLabel: 'No expired batches still in stock',
        tone: 'danger',
        href: () =>
            alertRoutes.index({
                query: { filter: { type: 'expired', state: 'open' } },
            }).url,
    },
    batches_near_expiry: {
        zeroLabel: 'Nothing expiring soon',
        tone: 'pending',
        href: () =>
            alertRoutes.index({
                query: { filter: { type: 'near_expiry', state: 'open' } },
            }).url,
    },
    products_low_stock: {
        zeroLabel: 'No products below threshold',
        tone: 'pending',
        href: () =>
            alertRoutes.index({
                query: { filter: { type: 'low_stock', state: 'open' } },
            }).url,
    },
    labels_past_dlu_unused: {
        zeroLabel: 'No unused labels past their use-by date',
        tone: 'expired',
        href: () => labels.index({ query: { filter: { overdue: true } } }).url,
    },
};

const DEFAULT_ORDER = [
    'cycles_awaiting_release',
    'cycles_rejected_no_non_conformity',
    'open_non_conformities',
    'batches_expired_in_stock',
    'batches_near_expiry',
    'products_low_stock',
    'labels_past_dlu_unused',
];

/** A releaser leads with release work; a stock_manager leads with stock and expiry (UI-30 role composition). */
const LEAD_KEYS_BY_ROLE: Record<string, string[]> = {
    releaser: ['cycles_awaiting_release'],
    stock_manager: [
        'products_low_stock',
        'batches_expired_in_stock',
        'batches_near_expiry',
    ],
};

function orderCards(
    cards: AttentionCard[],
    role: string | null,
): AttentionCard[] {
    const byKey = new Map(cards.map((card) => [card.key, card]));
    const leadKeys = role ? (LEAD_KEYS_BY_ROLE[role] ?? []) : [];
    const order = [
        ...leadKeys,
        ...DEFAULT_ORDER.filter((key) => !leadKeys.includes(key)),
    ];

    return order
        .map((key) => byKey.get(key))
        .filter((card): card is AttentionCard => card !== undefined);
}

function CardIcon({ tone, icon: Icon }: { tone: StatusTone; icon: LucideIcon }) {
    return (
        <span
            className={`inline-flex size-9 shrink-0 items-center justify-center rounded-lg ${TONE_CLASSES[tone]}`}
        >
            <Icon className="size-4.5" aria-hidden="true" />
        </span>
    );
}

function AttentionCardTile({ card }: { card: AttentionCard }) {
    const meta = CARD_META[card.key];

    if (!meta) {
        return null;
    }

    if (card.error) {
        return (
            <div className="flex items-center gap-3 rounded-lg border p-4">
                <CardIcon tone="danger" icon={TriangleAlert} />
                <div>
                    <p className="text-sm font-medium">Couldn't load this</p>
                    <p className="text-xs text-muted-foreground">
                        {card.label}
                    </p>
                </div>
            </div>
        );
    }

    if (card.count === 0) {
        return (
            <div className="flex items-center gap-3 rounded-lg border p-4">
                <CardIcon tone="success" icon={CheckCircle2} />
                <p className="text-sm font-medium">{meta.zeroLabel}</p>
            </div>
        );
    }

    return (
        <div
            data-testid={`attention-card-${card.key}`}
            className="flex items-center justify-between gap-3 rounded-lg border p-4"
        >
            <div className="flex items-center gap-3">
                <CardIcon tone={meta.tone} icon={TriangleAlert} />
                <div>
                    <p className="text-2xl font-semibold tabular-nums">
                        {card.count}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {card.label}
                    </p>
                </div>
            </div>
            {/* A generic "View" name, not the card's own label — the label
                text always contains a domain noun (Cycles, Products, ...)
                that collides with getByRole('link', { name }) lookups for
                the sidebar nav item of the same name on this same page. */}
            <Button asChild variant="ghost" size="sm">
                <Link href={meta.href()}>View</Link>
            </Button>
        </div>
    );
}

export function AttentionQueueSkeleton() {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {Array.from({ length: 7 }).map((_, i) => (
                <Skeleton key={i} className="h-[68px] rounded-lg" />
            ))}
        </div>
    );
}

export function AttentionQueue({
    cards,
    role,
}: {
    cards: AttentionCard[];
    role: string | null;
}) {
    const ordered = orderCards(cards, role);

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {ordered.map((card) => (
                <AttentionCardTile key={card.key} card={card} />
            ))}
        </div>
    );
}
