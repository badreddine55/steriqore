<?php

namespace App\Domain\Reporting\Enums;

/**
 * UI-29 added Expired — the underlying enum values stay the mobile API's
 * existing pending/processing/completed/failed contract (renaming to the
 * brief's queued/running/ready/expired wording would be a breaking change
 * for no real benefit; the web UI maps to friendlier labels itself).
 */
enum DataExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
