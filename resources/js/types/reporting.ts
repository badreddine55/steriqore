/** Mirrors App\Domain\Reporting\Data\AttentionCardData exactly. */
export type AttentionCard = {
    key: string;
    label: string;
    count: number;
    error: boolean;
};

/** Mirrors App\Domain\Reporting\Data\OnboardingStepData exactly. */
export type OnboardingStep = {
    key: string;
    label: string;
    done: boolean;
    can_act: boolean;
};

/** Mirrors App\Domain\Reporting\Data\TodayCycleData exactly. */
export type TodayCycle = {
    id: string;
    cycle_number: number;
    device_name: string;
    status: string;
    release_decision: string | null;
};

/** Mirrors App\Domain\Reporting\Data\RecentProcedureData exactly. */
export type RecentProcedure = {
    label_id: string;
    procedure: string;
    used_at: string;
};

/** Mirrors App\Domain\Reporting\Data\DashboardData exactly. */
export type DashboardData = {
    is_new_practice: boolean;
    onboarding_steps: OnboardingStep[];
    attention_cards: AttentionCard[];
    today_cycles: TodayCycle[];
    today_cycles_error: boolean;
    my_recent_procedures: RecentProcedure[];
    my_recent_procedures_error: boolean;
};

/** Mirrors App\Domain\Reporting\Data\DataExportRequestData exactly. */
export type DataExportRequest = {
    id: string;
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'expired';
    requested_by_name: string;
    requested_at: string;
    error: string | null;
    completed_at: string | null;
    size_bytes: number | null;
    record_count: number | null;
    file_count: number | null;
    table_manifest: Record<string, number> | null;
    expires_at: string | null;
};
