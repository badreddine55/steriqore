<?php

namespace App\Domain\Labeling\Enums;

/**
 * created -> printed -> used | expired | recalled | voided (CLAUDE.md).
 * S3-ANA-04 only implements created <-> printed; the scan endpoint
 * (S3-ANA-05) reaches Used/Expired, and recall/void are S4 non-conformity
 * territory. Defined in full now since it IS the label state machine.
 */
enum LabelStatus: string
{
    case Created = 'created';
    case Printed = 'printed';
    case Used = 'used';
    case Expired = 'expired';
    case Recalled = 'recalled';
    case Voided = 'voided';
}
