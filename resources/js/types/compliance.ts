/** Mirrors App\Domain\Compliance\Data\NonConformityData exactly. */
export type NonConformity = {
    id: string;
    subject_type: string;
    subject_id: string;
    description: string;
    raised_by_user_id: string;
    raised_at: string;
    resolved_by_user_id: string | null;
    resolved_at: string | null;
    resolution: string | null;
};

export type NonConformityFilters = {
    subject_type?: string;
};

/** Mirrors RaiseNonConformityAction::previewBlastRadius's return shape. */
export type NonConformityBlastRadius = {
    labels_to_recall: number;
    batches_to_quarantine: number;
};

/** Mirrors App\Domain\Compliance\Data\AuditEventData exactly. */
export type AuditEvent = {
    id: string;
    actor_id: string | null;
    actor_label_snapshot: string | null;
    action: string;
    subject_type: string;
    subject_id: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    reason: string | null;
    occurred_at: string;
};

export type AuditEventFilters = {
    actor_id?: string;
    action?: string;
    subject_type?: string;
    from?: string;
    to?: string;
};
