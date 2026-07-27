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
