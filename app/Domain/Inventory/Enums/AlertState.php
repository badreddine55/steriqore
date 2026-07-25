<?php

namespace App\Domain\Inventory\Enums;

enum AlertState: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
