<?php

namespace App\Domain\Reporting\Enums;

enum DataExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
