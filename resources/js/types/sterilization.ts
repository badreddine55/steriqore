/** Mirrors App\Domain\Sterilization\Data\CycleData exactly. */
export type Cycle = {
    id: string;
    device_id: string;
    device_program_id: string | null;
    operator_id: string;
    cycle_number: number;
    status: string;
    started_at: string | null;
    completed_at: string | null;
    notes: string | null;
};

export type CycleFilters = {
    device_id?: string;
    status?: string;
    operator_id?: string;
    q?: string;
    from?: string;
    to?: string;
};

/** Mirrors App\Domain\Sterilization\Data\CycleItemData exactly. */
export type CycleItem = {
    id: string;
    cycle_id: string;
    batch_id: string | null;
    description: string;
    sequence_in_cycle: number;
};

/** Mirrors App\Domain\Sterilization\Data\ControlTestData exactly. */
export type ControlTest = {
    id: string;
    cycle_id: string;
    type: string;
    result: string;
    performed_at: string;
    notes: string | null;
};

/** Mirrors App\Domain\Sterilization\Data\CycleAttachmentData exactly. */
export type CycleAttachment = {
    id: string;
    file_name: string;
    mime_type: string | null;
    size: number;
    url: string;
    created_at: string;
};

/** Mirrors App\Domain\Sterilization\Data\CycleReleaseData exactly. */
export type CycleRelease = {
    id: string;
    cycle_id: string;
    decision: string;
    released_by_user_id: string;
    released_at: string;
    reason: string | null;
};
