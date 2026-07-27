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
