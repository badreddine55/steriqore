<?php

namespace App\Domain\Sterilization\Enums;

enum ControlTestResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';
}
