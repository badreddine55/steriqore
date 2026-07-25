<?php

namespace App\Domain\Purchasing\Enums;

/**
 * draft -> ordered -> partially_received -> received -> closed | cancelled
 * (CLAUDE.md). Transitions are enforced in PurchaseOrderTransitions, not
 * here — they depend on line quantities, not just the current status.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
