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
