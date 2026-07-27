/** Mirrors App\Domain\Equipment\Data\DeviceProgramData exactly. */
export type DeviceProgram = {
    id: string;
    device_id: string;
    name: string;
    target_temperature_celsius: number;
    plateau_minutes: number;
    is_active: boolean;
};
