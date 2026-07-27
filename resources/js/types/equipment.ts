/** Mirrors App\Domain\Equipment\Data\DeviceProgramData exactly. */
export type DeviceProgram = {
    id: string;
    device_id: string;
    name: string;
    target_temperature_celsius: number;
    plateau_minutes: number;
    is_active: boolean;
};

/** Mirrors App\Domain\Equipment\Data\DeviceData exactly. */
export type Device = {
    id: string;
    site_id: string;
    name: string;
    serial_number: string;
    kind: string;
    status: string;
    manufacturer: string | null;
    model: string | null;
    commissioned_at: string | null;
    decommissioned_at: string | null;
    notes: string | null;
};

export type DeviceFilters = {
    name?: string;
    site_id?: string;
    status?: string;
    kind?: string;
};

/** Mirrors App\Domain\Equipment\Data\MaintenanceRecordData exactly. */
export type MaintenanceRecord = {
    id: string;
    device_id: string;
    recorded_by_user_id: string | null;
    kind: string;
    technician: string | null;
    performed_at: string;
    next_due_at: string | null;
    description: string | null;
};
