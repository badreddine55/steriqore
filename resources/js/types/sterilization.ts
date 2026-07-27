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
