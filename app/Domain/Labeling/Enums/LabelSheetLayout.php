<?php

namespace App\Domain\Labeling\Enums;

enum LabelSheetLayout: string
{
    case A4_24 = 'a4_24';
    case A4_65 = 'a4_65';
    case Roll = 'roll';

    public function label(): string
    {
        return match ($this) {
            self::A4_24 => 'A4 sheet, 24 labels (4 x 6)',
            self::A4_65 => 'A4 sheet, 65 labels (5 x 13)',
            self::Roll => 'Continuous roll',
        };
    }

    /**
     * @return array{0: int, 1: int} [columns, rows] — how a test-print
     *                               sample sheet tiles the layout.
     */
    public function grid(): array
    {
        return match ($this) {
            self::A4_24 => [4, 6],
            self::A4_65 => [5, 13],
            self::Roll => [1, 10],
        };
    }
}
