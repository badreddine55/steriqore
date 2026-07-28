<?php

namespace App\Domain\Labeling\Enums;

/**
 * Both QR and DataMatrix always print on every label (CLAUDE.md's LABELS
 * section) — this only controls which is visually larger/first, never
 * which one actually prints.
 */
enum PrimaryLabelCode: string
{
    case DataMatrix = 'datamatrix';
    case Qr = 'qr';
}
