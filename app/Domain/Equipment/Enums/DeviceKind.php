<?php

namespace App\Domain\Equipment\Enums;

enum DeviceKind: string
{
    case Autoclave = 'autoclave';
    case WasherDisinfector = 'washer_disinfector';
    case Sealer = 'sealer';
    case Other = 'other';
}
