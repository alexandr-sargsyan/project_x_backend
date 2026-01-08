<?php

namespace App\Enums;

enum PlatformEnum: string
{
    case INSTAGRAM = 'instagram';
    case TIKTOK = 'tiktok';
    case YOUTUBE = 'youtube';

    /**
     * Получить все значения enum'а
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Определить platform по URL
     */
    public static function fromUrl(string $url): ?self
    {
        $url = strtolower($url);

        if (str_contains($url, 'instagram.com')) {
            return self::INSTAGRAM;
        }

        if (str_contains($url, 'tiktok.com')) {
            return self::TIKTOK;
        }

        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return self::YOUTUBE;
        }

        return null;
    }
}

