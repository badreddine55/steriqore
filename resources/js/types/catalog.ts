/** Mirrors App\Domain\Catalog\Data\ProductCategoryData exactly. */
export type ProductCategory = {
    id: string;
    parent_category_id: string | null;
    name: string;
};

/** Mirrors App\Domain\Catalog\Data\ProductData exactly. */
export type Product = {
    id: string;
    category_id: string | null;
    default_location_id: string | null;
    name: string;
    reference: string;
    unit: string;
    min_threshold: number;
    is_sterilizable: boolean;
    barcode: string | null;
};

export type ProductFilters = {
    name?: string;
    reference?: string;
    category_id?: string;
    is_sterilizable?: string;
};
