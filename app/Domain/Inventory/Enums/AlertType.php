<?php

namespace App\Domain\Inventory\Enums;

/**
 * missing_test (CLAUDE.md's full list) is deferred — see OQ-7 in
 * docs/open-questions.md: determining "missing" needs a per-tenant test
 * frequency rule table that doesn't exist yet and would mean inventing a
 * regulatory business rule (how often is a Bowie-Dick/biological test
 * required?) instead of flagging it.
 */
enum AlertType: string
{
    case LowStock = 'low_stock';
    case NearExpiry = 'near_expiry';
    case Expired = 'expired';
    case FailedCycle = 'failed_cycle';
}
