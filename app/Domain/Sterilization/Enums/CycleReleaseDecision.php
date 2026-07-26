<?php

namespace App\Domain\Sterilization\Enums;

enum CycleReleaseDecision: string
{
    case Compliant = 'compliant';
    case Rejected = 'rejected';
}
