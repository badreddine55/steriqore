import type { LucideIcon } from 'lucide-react';
import {
    CalendarOff,
    CheckCircle2,
    CircleDashed,
    CircleDot,
    Clock,
    ShieldAlert,
    XCircle,
} from 'lucide-react';

/**
 * The strict status palette (see resources/css/app.css) — the only place
 * saturated color appears anywhere in the app. Every domain enum below
 * maps onto exactly one of these seven tones; never introduce a new hue
 * for a new enum, extend this mapping instead. "active" is deliberately
 * NOT one of the six named compliance states (released/compliant,
 * rejected, pending release, quarantined/recalled, expired, draft) — it's
 * the neutral institutional accent, for states that are simply
 * "in progress" rather than a compliance-relevant outcome.
 */
export type StatusTone =
    | 'draft'
    | 'pending'
    | 'success'
    | 'danger'
    | 'contained'
    | 'expired'
    | 'active';

export interface StatusMeta {
    tone: StatusTone;
    label: string;
    icon: LucideIcon;
}

const TONE_ICON: Record<StatusTone, LucideIcon> = {
    draft: CircleDashed,
    pending: Clock,
    success: CheckCircle2,
    danger: XCircle,
    contained: ShieldAlert,
    expired: CalendarOff,
    active: CircleDot,
};

function meta(tone: StatusTone, label: string): StatusMeta {
    return { tone, label, icon: TONE_ICON[tone] };
}

/** Cycle: draft -> running -> completed -> awaiting_release -> released | rejected */
export const cycleStatus: Record<string, StatusMeta> = {
    draft: meta('draft', 'Draft'),
    running: meta('active', 'Running'),
    completed: meta('active', 'Completed'),
    awaiting_release: meta('pending', 'Pending release'),
    released: meta('success', 'Released'),
    rejected: meta('danger', 'Rejected'),
};

/** Cycle release decision: compliant | rejected */
export const cycleReleaseDecision: Record<string, StatusMeta> = {
    compliant: meta('success', 'Compliant'),
    rejected: meta('danger', 'Rejected'),
};

/** Label: created -> printed -> used | expired | recalled | voided */
export const labelStatus: Record<string, StatusMeta> = {
    created: meta('draft', 'Created'),
    printed: meta('active', 'Printed'),
    used: meta('success', 'Used'),
    expired: meta('expired', 'Expired'),
    recalled: meta('contained', 'Recalled'),
    voided: meta('draft', 'Voided'),
};

/** Batch: active | quarantined */
export const batchStatus: Record<string, StatusMeta> = {
    active: meta('active', 'Active'),
    quarantined: meta('contained', 'Quarantined'),
};

/** Control test result: pass | fail */
export const controlTestResult: Record<string, StatusMeta> = {
    pass: meta('success', 'Pass'),
    fail: meta('danger', 'Fail'),
};

/** Device: active | maintenance | decommissioned */
export const deviceStatus: Record<string, StatusMeta> = {
    active: meta('active', 'Active'),
    maintenance: meta('pending', 'Maintenance'),
    decommissioned: meta('draft', 'Decommissioned'),
};

/** Device kind — a category, not a status; no tone/badge, just a label. */
export const deviceKindLabel: Record<string, string> = {
    autoclave: 'Autoclave',
    washer_disinfector: 'Washer-disinfector',
    sealer: 'Sealer',
    other: 'Other',
};

/** Purchase order: draft -> ordered -> partially_received -> received -> closed | cancelled */
export const purchaseOrderStatus: Record<string, StatusMeta> = {
    draft: meta('draft', 'Draft'),
    ordered: meta('active', 'Ordered'),
    partially_received: meta('pending', 'Partially received'),
    received: meta('success', 'Received'),
    closed: meta('draft', 'Closed'),
    cancelled: meta('danger', 'Cancelled'),
};

/** Data export request: pending -> processing -> completed | failed */
export const dataExportStatus: Record<string, StatusMeta> = {
    pending: meta('draft', 'Pending'),
    processing: meta('active', 'Processing'),
    completed: meta('success', 'Completed'),
    failed: meta('danger', 'Failed'),
};

/** Alert severity: info | warning | critical */
export const alertSeverity: Record<string, StatusMeta> = {
    info: meta('active', 'Info'),
    warning: meta('pending', 'Warning'),
    critical: meta('danger', 'Critical'),
};

/** Control test type — a category, not a status; no tone/badge, just a label. */
export const controlTestTypeLabel: Record<string, string> = {
    vacuum: 'Vacuum',
    bowie_dick: 'Bowie-Dick',
    helix: 'Helix',
    biological: 'Biological',
};
