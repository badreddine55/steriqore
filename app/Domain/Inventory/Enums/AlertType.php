<?php

namespace App\Domain\Inventory\Enums;

/**
 * failed_cycle and missing_test (CLAUDE.md's full list) belong here once
 * S3 ships cycles and control tests — not before.
 */
enum AlertType: string
{
    case LowStock = 'low_stock';
    case NearExpiry = 'near_expiry';
    case Expired = 'expired';
}
