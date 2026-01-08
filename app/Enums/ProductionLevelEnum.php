<?php

namespace App\Enums;

enum ProductionLevelEnum: string
{
    case LOW = 'low';
    case MID = 'mid';
    case HIGH = 'high';

    /**
     * Получить все значения enum'а
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

