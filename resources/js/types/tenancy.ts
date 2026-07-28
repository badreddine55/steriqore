/** Mirrors App\Domain\Tenancy\Data\SiteData exactly. */
export type Site = {
    id: string;
    name: string;
    address_line1: string | null;
    address_line2: string | null;
    postal_code: string | null;
    city: string | null;
    country_code: string | null;
    timezone: string;
    is_primary: boolean;
    archived_at: string | null;
    rooms_count: number | null;
    storage_locations_count: number | null;
    devices_count: number | null;
};

/** Mirrors App\Domain\Tenancy\Data\RoomData exactly. */
export type Room = {
    id: string;
    site_id: string;
    name: string;
    kind: string | null;
    archived_at: string | null;
    storage_locations_count: number | null;
};

/** Mirrors App\Domain\Tenancy\Data\StorageLocationData exactly. */
export type StorageLocation = {
    id: string;
    site_id: string;
    room_id: string | null;
    parent_location_id: string | null;
    name: string;
    kind: string | null;
    archived_at: string | null;
    units_on_hand: number;
};
