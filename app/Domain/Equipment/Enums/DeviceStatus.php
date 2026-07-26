<?php

namespace App\Domain\Equipment\Enums;

/**
 * A device is never deleted while any cycle references it (soft-delete
 * only), but it can be taken out of service without deleting anything —
 * that's what Maintenance/Decommissioned are for. S3-ANA-02 will forbid
 * starting a new cycle on a device that isn't Active.
 */
enum DeviceStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Decommissioned = 'decommissioned';
}
