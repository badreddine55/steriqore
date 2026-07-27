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
