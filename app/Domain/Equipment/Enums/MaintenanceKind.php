<?php

namespace App\Domain\Equipment\Enums;

enum MaintenanceKind: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Calibration = 'calibration';
}
