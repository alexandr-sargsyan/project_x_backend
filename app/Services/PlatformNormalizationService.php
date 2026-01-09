<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PlatformNormalizationService
{
    /**
     * Нормализует URL видео и возвращает структурированные данные
     *
     * @param string $url
     * @return array{platform: string|null, platform_video_id: string|null, normalized: bool}
     */
    public function normalizeUrl(string $url): array
    {
        $platform = $this->detectPlatform($url);

        if (!$platform) {
            return [
                'platform' => null,
                'platform_video_id' => null,
                'normalized' => false,
            ];
        }

        $videoId = match($platform) {
            'youtube' => $this->extractYouTubeId($url),
            'tiktok' => $this->extractTikTokId($url),
            'instagram' => $this->extractInstagramId($url),
            default => null,
        };

        return [
            'platform' => $videoId ? $platform : null,
            'platform_video_id' => $videoId,
            'normalized' => !empty($videoId),
        ];
    }

    /**
     * Определяет платформу по URL
     */
    private function detectPlatform(string $url): ?string
    {
        $url = strtolower(trim($url));

        if (preg_match('/youtube\.com|youtu\.be/', $url)) {
            return 'youtube';
        }

        if (preg_match('/tiktok\.com/', $url)) {
            return 'tiktok';
        }

        if (preg_match('/instagram\.com/', $url)) {
            return 'instagram';
        }

        return null;
    }

    /**
     * Извлекает video ID для YouTube
     */
    private function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/m\.youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Извлекает video ID для TikTok
     */
    private function extractTikTokId(string $url): ?string
    {
        // Стандартный формат: /@username/video/{ID}
        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        // Короткие ссылки требуют редиректа
        if (preg_match('/vm\.tiktok\.com|t\.tiktok\.com/', $url)) {
            $fullUrl = $this->resolveShortUrl($url);
            if ($fullUrl) {
                return $this->extractTikTokId($fullUrl);
            }
        }

        // Мобильная версия
        if (preg_match('/m\.tiktok\.com\/v\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Извлекает post ID для Instagram
     */
    private function extractInstagramId(string $url): ?string
    {
        $patterns = [
            '/instagram\.com\/p\/([a-zA-Z0-9_-]+)/',
            '/instagram\.com\/reel\/([a-zA-Z0-9_-]+)/',
            '/instagram\.com\/tv\/([a-zA-Z0-9_-]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Разрешает короткую ссылку в полный URL
     */
    private function resolveShortUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->head($url);
            $location = $response->header('Location');
            
            if ($location) {
                return $location;
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки при разрешении коротких ссылок
        }

        return null;
    }
}

