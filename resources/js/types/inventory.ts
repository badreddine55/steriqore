/** Mirrors App\Domain\Inventory\Data\BatchListEntryData exactly. */
export type Batch = {
    id: string;
    product_id: string;
    product_name: string;
    supplier_id: string | null;
    supplier_name: string | null;
    batch_number: string;
    expiry_date: string | null;
    received_at: string;
    status: string;
    qty_on_hand: number;
};

export type BatchFilters = {
    product_id?: string;
    supplier_id?: string;
    status?: string;
    expiry?: string;
};

/** Mirrors App\Domain\Inventory\Data\StockLevelEntryData exactly. */
export type StockLevelEntry = {
    id: string;
    batch_id: string;
    batch_number: string;
    product_id: string;
    product_name: string;
    location_id: string;
    location_name: string;
    quantity: number;
};

export type StockLevelFilters = {
    product_id?: string;
    location_id?: string;
    in_stock_only?: string;
};

/** Mirrors App\Domain\Inventory\Data\StockMovementLogEntryData exactly. */
export type StockMovementLogEntry = {
    id: string;
    type: string;
    batch_id: string;
    batch_number: string;
    product_id: string;
    product_name: string;
    location_id: string;
    location_name: string;
    qty: number;
    group_id: string | null;
    reason: string | null;
    actor_name: string | null;
    occurred_at: string;
};

export type StockMovementFilters = {
    type?: string;
    product_id?: string;
    location_id?: string;
    from?: string;
    to?: string;
};

/** Mirrors App\Domain\Inventory\Data\AlertData exactly. */
export type Alert = {
    id: string;
    type: string;
    severity: string;
    state: string;
    subject_type: string;
    subject_id: string;
    message: string;
    resolved_at: string | null;
    resolved_by_name: string | null;
    created_at: string;
};

export type AlertFilters = {
    type?: string;
    severity?: string;
    state?: string;
};
