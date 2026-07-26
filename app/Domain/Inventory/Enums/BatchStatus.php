<?php

namespace App\Domain\Inventory\Enums;

/**
 * Quarantined is set by RaiseNonConformityAction (S4-ANA-05) — a
 * quarantined batch can no longer be issued (IssueStockAction blocks it),
 * though its existing stock ledger rows are untouched (invariant #1:
 * stock is derived from movements, never mutated directly).
 */
enum BatchStatus: string
{
    case Active = 'active';
    case Quarantined = 'quarantined';
}
