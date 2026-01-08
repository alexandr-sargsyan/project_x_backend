<?php

namespace App\Enums;

enum PacingEnum: string
{
    case SLOW = 'slow';
    case FAST = 'fast';
    case MIXED = 'mixed';

    /**
     * Получить все значения enum'а
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

