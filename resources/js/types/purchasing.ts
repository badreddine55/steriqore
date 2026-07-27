/** Mirrors App\Domain\Purchasing\Data\SupplierData exactly. */
export type Supplier = {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
};

/** Mirrors App\Domain\Purchasing\Data\SupplierProductData exactly. */
export type SupplierProduct = {
    id: string;
    supplier_id: string;
    product_id: string;
    supplier_reference: string | null;
    pack_size: number;
    price: string | null;
};

export type SupplierFilters = {
    name?: string;
};

/** Mirrors App\Domain\Purchasing\Data\PurchaseOrderLineData exactly. */
export type PurchaseOrderLine = {
    id: string;
    product_id: string;
    qty_ordered: number;
    qty_received: number;
    unit_price: string | null;
};

/** Mirrors App\Domain\Purchasing\Data\PurchaseOrderData exactly. */
export type PurchaseOrder = {
    id: string;
    supplier_id: string;
    status: string;
    ordered_at: string | null;
    expected_at: string | null;
    lines: PurchaseOrderLine[];
};
