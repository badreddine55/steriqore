<?php

namespace App\Domain\Inventory\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Count = 'count';
}
