/** Mirrors App\Domain\Labeling\Data\LabelData exactly. */
export type Label = {
    id: string;
    urn: string;
    cycle_item_id: string;
    status: string;
    packaging_type: string;
    storage_condition: string;
    shelf_life_days: number;
    sterilized_at: string;
    use_by_date: string;
    print_counter: number;
};

/** Mirrors App\Domain\Labeling\Data\DluRuleData exactly. */
export type DluRule = {
    id: string;
    packaging_type: string;
    storage_condition: string;
    shelf_life_days: number;
};

/** Mirrors App\Domain\Labeling\Data\LabelListEntryData exactly. */
export type LabelListEntry = {
    id: string;
    status: string;
    cycle_number: number;
    device_name: string;
    description: string;
    use_by_date: string;
    print_counter: number;
};

export type LabelFilters = {
    status?: string;
    device_id?: string;
};

/** Mirrors App\Domain\Labeling\Data\LabelPrintData exactly. */
export type LabelPrint = {
    id: string;
    label_id: string;
    printed_by_user_id: string;
    sequence: number;
    reason: string | null;
    printed_at: string;
};

/** Mirrors App\Domain\Traceability\Data\LabelUsageData exactly. */
export type LabelUsage = {
    id: string;
    label_id: string;
    patient_id: string;
    practitioner_id: string;
    procedure: string;
    used_at: string;
    notes: string | null;
};
