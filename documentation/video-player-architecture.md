# Архитектура единой системы воспроизведения видео
## Filmmaker Reference Platform - Unified Video Player

**Версия:** 1.0  
**Дата:** 2026-01-09  
**Статус:** План реализации

---

## Содержание

1. [Обзор и цели](#1-обзор-и-цели)
2. [Архитектурные принципы](#2-архитектурные-принципы)
3. [Backend: Нормализация источников](#3-backend-нормализация-источников)
4. [Frontend: Единый плеер](#4-frontend-единый-плеер)
5. [Детальная реализация Backend](#5-детальная-реализация-backend)
6. [Детальная реализация Frontend](#6-детальная-реализация-frontend)
7. [Интеграция и тестирование](#7-интеграция-и-тестирование)
8. [Расширяемость и будущие улучшения](#8-расширяемость-и-будущие-улучшения)

---

## 1. Обзор и цели

### 1.1. Главная цель

Создать единую систему воспроизведения видео, которая:
- **Абстрагирует** различия между платформами (YouTube, TikTok, Instagram)
- **Предоставляет** единый пользовательский опыт для всех видео
- **Скрывает** технические детали интеграции от пользователя
- **Обеспечивает** гибкость и расширяемость для добавления новых платформ

### 1.2. Проблема

Разные платформы имеют:
- Разные форматы URL
- Разные способы встраивания (iframe, embed, oEmbed)
- Разные API для управления воспроизведением
- Разные параметры настройки (autoplay, controls, mute)
- Разные ограничения и требования

**Решение:** Двухслойная архитектура с нормализацией на backend и единым интерфейсом на frontend.

### 1.3. Поддерживаемые платформы

| Платформа | Статус | Метод встраивания |
|-----------|--------|-------------------|
| YouTube | ✅ Полная поддержка | iframe embed API |
| YouTube Shorts | ✅ Полная поддержка | iframe embed API (тот же, что YouTube) |
| TikTok | ✅ Полная поддержка | iframe player v1 API |
| Instagram | ⚠️ Базовая поддержка | embed.js (стандартный embed) |

---

## 2. Архитектурные принципы

### 2.1. Разделение ответственности

```
┌─────────────────────────────────────────────────────────┐
│                    Backend Layer                         │
│  ┌───────────────────────────────────────────────────┐  │
│  │  PlatformNormalizationService                     │  │
│  │  - Определяет платформу                           │  │
│  │  - Извлекает video ID                             │  │
│  │  - Нормализует данные                             │  │
│  └───────────────────────────────────────────────────┘  │
│                          ↓                               │
│  ┌───────────────────────────────────────────────────┐  │
│  │  VideoReference Model                             │  │
│  │  - platform: string                               │  │
│  │  - platform_video_id: string                      │  │
│  │  - source_url: string                             │  │
│  └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
                    JSON API Response
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    Frontend Layer                       │
│  ┌───────────────────────────────────────────────────┐  │
│  │  UnifiedVideoPlayer Component                     │  │
│  │  - Принимает platform, platform_video_id          │  │
│  │  - Рендерит нужный iframe/embed                   │  │
│  │  - Обеспечивает единый UI                         │  │
│  └───────────────────────────────────────────────────┘  │
│                          ↓                               │
│  ┌───────────────────────────────────────────────────┐  │
│  │  VideoOverlay Component                           │  │
│  │  - Play/Pause кнопки                             │  │
│  │  - Mute/Unmute кнопки                             │  │
│  │  - Единый стиль для всех платформ                 │  │
│  └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### 2.2. Принцип нормализации

**Backend** приводит все разнообразие URL к единому формату:

```php
// Входные данные (разные форматы)
"https://www.youtube.com/watch?v=ABC123"
"https://youtu.be/ABC123"
"https://www.youtube.com/shorts/ABC123"
"https://www.tiktok.com/@user/video/1234567890"
"https://www.instagram.com/p/ABC123/"

// Выходные данные (единый формат)
[
    'platform' => 'youtube',
    'platform_video_id' => 'ABC123',
    'source_url' => 'https://www.youtube.com/watch?v=ABC123'
]
```

### 2.3. Принцип единого интерфейса

**Frontend** скрывает различия платформ за единым компонентом:

```jsx
// Пользователь видит одно и то же
<UnifiedVideoPlayer 
    platform="youtube"
    platformVideoId="ABC123"
/>

<UnifiedVideoPlayer 
    platform="tiktok"
    platformVideoId="1234567890"
/>

// Внутри компонента - разная реализация, но одинаковый UI
```

---

## 3. Backend: Нормализация источников

### 3.1. Общая схема работы

```
1. Администратор вводит source_url в админ-панели
   ↓
2. Backend получает source_url через API
   ↓
3. PlatformNormalizationService определяет платформу
   ↓
4. Извлекается platform_video_id
   ↓
5. Определяется platform (youtube/tiktok/instagram)
   ↓
6. Данные сохраняются в БД
   ↓
7. API возвращает нормализованные данные
```

### 3.2. Определение платформы

#### 3.2.1. YouTube и YouTube Shorts

**URL паттерны:**
- `https://www.youtube.com/watch?v={VIDEO_ID}`
- `https://youtu.be/{VIDEO_ID}`
- `https://www.youtube.com/shorts/{VIDEO_ID}`
- `https://m.youtube.com/watch?v={VIDEO_ID}`

**Алгоритм извлечения:**
1. Проверить наличие `youtube.com` или `youtu.be` в URL
2. Извлечь video ID:
   - Для `watch?v=` → взять значение параметра `v`
   - Для `youtu.be/` → взять часть после последнего `/`
   - Для `shorts/` → взять часть после `shorts/`
3. Валидировать: video ID должен быть 11 символов (алфавитно-цифровой)

**Результат:**
```php
[
    'platform' => 'youtube',
    'platform_video_id' => 'ABC123XYZ45',
    'normalized' => true
]
```

#### 3.2.2. TikTok

**URL паттерны:**
- `https://www.tiktok.com/@username/video/{VIDEO_ID}`
- `https://vm.tiktok.com/{SHORT_ID}` (требует редиректа)
- `https://m.tiktok.com/v/{VIDEO_ID}`

**Алгоритм извлечения:**
1. Проверить наличие `tiktok.com` в URL
2. Извлечь video ID:
   - Для `@username/video/{VIDEO_ID}` → взять числовую часть после `/video/`
   - Для коротких ссылок → выполнить HTTP HEAD запрос для получения полного URL
3. Валидировать: video ID должен быть числом (обычно 19 цифр)

**Результат:**
```php
[
    'platform' => 'tiktok',
    'platform_video_id' => '1234567890123456789',
    'normalized' => true
]
```

#### 3.2.3. Instagram

**URL паттерны:**
- `https://www.instagram.com/p/{POST_ID}/`
- `https://www.instagram.com/reel/{REEL_ID}/`
- `https://www.instagram.com/tv/{IGTV_ID}/`

**Алгоритм извлечения:**
1. Проверить наличие `instagram.com` в URL
2. Извлечь post ID:
   - Для `/p/` → взять часть после `/p/` до следующего `/`
   - Для `/reel/` → взять часть после `/reel/` до следующего `/`
   - Для `/tv/` → взять часть после `/tv/` до следующего `/`
3. Валидировать: post ID должен быть алфавитно-цифровой строкой

**Результат:**
```php
[
    'platform' => 'instagram',
    'platform_video_id' => 'ABC123XYZ',
    'normalized' => true
]
```

### 3.3. Структура данных в БД

#### 3.3.1. Изменения в таблице `video_references`

Добавить поле:
```sql
ALTER TABLE video_references 
ADD COLUMN platform_video_id VARCHAR(255) NULL;
```

**Описание полей:**
- `platform` (string, nullable) - платформа: 'youtube', 'tiktok', 'instagram'
- `platform_video_id` (string, nullable) - нормализованный ID видео на платформе
- `source_url` (string) - оригинальная ссылка (сохраняется для справки)

**Индексы:**
- Индекс на `platform` для быстрой фильтрации
- Составной индекс на `(platform, platform_video_id)` для уникальности

#### 3.3.2. Обновление модели VideoReference

```php
// app/Models/VideoReference.php
protected $fillable = [
    // ... существующие поля
    'platform_video_id',
];

// Добавить accessor для проверки нормализации
public function isNormalized(): bool
{
    return !empty($this->platform) && !empty($this->platform_video_id);
}
```

### 3.4. API Response формат

**Стандартный ответ при получении video reference:**
```json
{
    "data": {
        "id": 1,
        "title": "Example Video",
        "source_url": "https://www.youtube.com/watch?v=ABC123",
        "platform": "youtube",
        "platform_video_id": "ABC123",
        "preview_url": "...",
        "category": {...},
        "tags": [...],
        "tutorials": [...]
    }
}
```

---

## 4. Frontend: Единый плеер

### 4.1. Архитектура компонентов

```
VideoDetailPage
    ↓
UnifiedVideoPlayer (главный компонент)
    ├── YouTubePlayer (если platform === 'youtube')
    ├── TikTokPlayer (если platform === 'tiktok')
    └── InstagramPlayer (если platform === 'instagram')
    ↓
VideoOverlay (общий для всех)
    ├── PlayButton
    ├── PauseButton
    ├── MuteButton
    └── UnmuteButton
```

### 4.2. Единые параметры воспроизведения

**Для всех платформ:**
- Пропорции: 9:16 (вертикальные видео) или 16:9 (горизонтальные)
- Автоплей: включен (muted)
- Контролы платформы: скрыты
- Звук: по умолчанию выключен
- Поведение: остановка при скролле/переключении

### 4.3. Специфичные настройки по платформам

#### 4.3.1. YouTube / YouTube Shorts

**iframe URL:**
```
https://www.youtube.com/embed/{VIDEO_ID}?controls=0&autoplay=1&mute=1&rel=0&playsinline=1&enablejsapi=1
```

**Параметры:**
- `controls=0` - скрыть встроенные контролы
- `autoplay=1` - автозапуск
- `mute=1` - без звука
- `rel=0` - не показывать связанные видео
- `playsinline=1` - воспроизведение внутри страницы на мобильных
- `enablejsapi=1` - включить JavaScript API для управления

**API управления:**
- YouTube IFrame Player API для программного управления

#### 4.3.2. TikTok

**iframe URL:**
```
https://www.tiktok.com/player/v1/{VIDEO_ID}?controls=0&autoplay=1&muted=1&loop=1&rel=0
```

**Параметры:**
- `controls=0` - скрыть встроенные контролы
- `autoplay=1` - автозапуск
- `muted=1` - без звука
- `loop=1` - зацикливание
- `rel=0` - не показывать рекомендации

**API управления:**
- TikTok Embedded Player API через postMessage

#### 4.3.3. Instagram

**Метод встраивания:**
```html
<blockquote class="instagram-media" 
            data-instgrm-permalink="{POST_URL}">
</blockquote>
<script async src="//www.instagram.com/embed.js"></script>
```

**Особенности:**
- Автоплей не поддерживается
- Контролы нельзя скрыть полностью
- Требуется клик пользователя для запуска

---

## 5. Детальная реализация Backend

### 5.1. Создание сервиса нормализации

#### 5.1.1. Структура сервиса

**Файл:** `app/Services/PlatformNormalizationService.php`

```php
<?php

namespace App\Services;

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
        // Определение платформы и извлечение ID
    }

    /**
     * Определяет платформу по URL
     */
    private function detectPlatform(string $url): ?string
    {
        // Логика определения
    }

    /**
     * Извлекает video ID для YouTube
     */
    private function extractYouTubeId(string $url): ?string
    {
        // Логика извлечения
    }

    /**
     * Извлекает video ID для TikTok
     */
    private function extractTikTokId(string $url): ?string
    {
        // Логика извлечения
    }

    /**
     * Извлекает post ID для Instagram
     */
    private function extractInstagramId(string $url): ?string
    {
        // Логика извлечения
    }
}
```

#### 5.1.2. Реализация определения платформы

**Метод `detectPlatform()`:**
```php
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
```

#### 5.1.3. Реализация извлечения YouTube ID

**Метод `extractYouTubeId()`:**
```php
private function extractYouTubeId(string $url): ?string
{
    // Паттерны для разных форматов URL
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}
```

#### 5.1.4. Реализация извлечения TikTok ID

**Метод `extractTikTokId()`:**
```php
private function extractTikTokId(string $url): ?string
{
    // Стандартный формат: /@username/video/{ID}
    if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $matches)) {
        return $matches[1];
    }
    
    // Короткие ссылки требуют редиректа
    if (preg_match('/vm\.tiktok\.com|t\.tiktok\.com/', $url)) {
        // Выполнить HEAD запрос для получения полного URL
        $fullUrl = $this->resolveShortUrl($url);
        if ($fullUrl) {
            return $this->extractTikTokId($fullUrl);
        }
    }
    
    return null;
}

private function resolveShortUrl(string $url): ?string
{
    // HTTP HEAD запрос для получения Location header
    // Использовать Guzzle или file_get_contents с контекстом
}
```

#### 5.1.5. Реализация извлечения Instagram ID

**Метод `extractInstagramId()`:**
```php
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
```

#### 5.1.6. Главный метод нормализации

**Метод `normalizeUrl()`:**
```php
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
```

### 5.2. Интеграция в контроллер

#### 5.2.1. Обновление StoreVideoReferenceRequest

**Валидация source_url:**
```php
'source_url' => ['required', 'url', 'max:2048'],
```

#### 5.2.2. Обновление VideoReferenceController

**Метод `store()`:**
```php
public function store(StoreVideoReferenceRequest $request): JsonResponse
{
    $validated = $request->validated();
    
    // Нормализация URL
    $normalizationService = app(PlatformNormalizationService::class);
    $normalized = $normalizationService->normalizeUrl($validated['source_url']);
    
    // Добавить нормализованные данные
    $validated['platform'] = $normalized['platform'];
    $validated['platform_video_id'] = $normalized['platform_video_id'];
    
    // Если не удалось нормализовать, сохранить как есть
    // (для будущей обработки или ручной настройки)
    
    // Создание записи
    $videoReference = VideoReference::create($validated);
    
    // Обработка тегов, tutorials и т.д.
    
    return response()->json([
        'data' => $videoReference->load(['category', 'tags', 'tutorials']),
    ], 201);
}
```

**Метод `update()`:**
```php
public function update(UpdateVideoReferenceRequest $request, string $id): JsonResponse
{
    $videoReference = VideoReference::findOrFail($id);
    $validated = $request->validated();
    
    // Если изменился source_url, перенормализовать
    if (isset($validated['source_url']) && 
        $validated['source_url'] !== $videoReference->source_url) {
        
        $normalizationService = app(PlatformNormalizationService::class);
        $normalized = $normalizationService->normalizeUrl($validated['source_url']);
        
        $validated['platform'] = $normalized['platform'];
        $validated['platform_video_id'] = $normalized['platform_video_id'];
    }
    
    $videoReference->update($validated);
    
    return response()->json([
        'data' => $videoReference->load(['category', 'tags', 'tutorials']),
    ]);
}
```

### 5.3. Миграция базы данных

**Файл:** `database/migrations/XXXX_XX_XX_add_platform_video_id_to_video_references.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_references', function (Blueprint $table) {
            $table->string('platform_video_id', 255)->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('video_references', function (Blueprint $table) {
            $table->dropColumn('platform_video_id');
        });
    }
};
```

### 5.4. Обновление модели VideoReference

```php
// app/Models/VideoReference.php

protected $fillable = [
    // ... существующие поля
    'platform_video_id',
];

/**
 * Проверка, нормализовано ли видео
 */
public function isNormalized(): bool
{
    return !empty($this->platform) && !empty($this->platform_video_id);
}

/**
 * Получить embed URL для встраивания
 */
public function getEmbedUrlAttribute(): ?string
{
    if (!$this->isNormalized()) {
        return null;
    }
    
    return match($this->platform) {
        'youtube' => "https://www.youtube.com/embed/{$this->platform_video_id}",
        'tiktok' => "https://www.tiktok.com/player/v1/{$this->platform_video_id}",
        'instagram' => $this->source_url, // Для Instagram используем source_url
        default => null,
    };
}
```

---

## 6. Детальная реализация Frontend

### 6.1. Структура компонентов

```
src/
  components/
    VideoPlayer/
      UnifiedVideoPlayer.jsx      # Главный компонент
      UnifiedVideoPlayer.css
      YouTubePlayer.jsx           # Реализация для YouTube
      TikTokPlayer.jsx            # Реализация для TikTok
      InstagramPlayer.jsx         # Реализация для Instagram
      VideoOverlay.jsx             # Общий overlay
      VideoOverlay.css
      useVideoPlayer.js            # Хук для управления плеером
```

### 6.2. Главный компонент UnifiedVideoPlayer

**Файл:** `src/components/VideoPlayer/UnifiedVideoPlayer.jsx`

```jsx
import React from 'react';
import YouTubePlayer from './YouTubePlayer';
import TikTokPlayer from './TikTokPlayer';
import InstagramPlayer from './InstagramPlayer';
import VideoOverlay from './VideoOverlay';
import './UnifiedVideoPlayer.css';

const UnifiedVideoPlayer = ({ 
  platform, 
  platformVideoId, 
  sourceUrl,
  autoplay = true,
  muted = true,
  loop = false,
}) => {
  // Определение, какой плеер использовать
  const renderPlayer = () => {
    if (!platform || !platformVideoId) {
      return <div className="video-error">Video not available</div>;
    }

    switch (platform) {
      case 'youtube':
        return (
          <YouTubePlayer
            videoId={platformVideoId}
            autoplay={autoplay}
            muted={muted}
            loop={loop}
          />
        );
      
      case 'tiktok':
        return (
          <TikTokPlayer
            videoId={platformVideoId}
            autoplay={autoplay}
            muted={muted}
            loop={loop}
          />
        );
      
      case 'instagram':
        return (
          <InstagramPlayer
            postId={platformVideoId}
            sourceUrl={sourceUrl}
          />
        );
      
      default:
        return <div className="video-error">Unsupported platform</div>;
    }
  };

  return (
    <div className="unified-video-player">
      <div className="video-container">
        {renderPlayer()}
      </div>
      <VideoOverlay platform={platform} />
    </div>
  );
};

export default UnifiedVideoPlayer;
```

**Стили:** `src/components/VideoPlayer/UnifiedVideoPlayer.css`

```css
.unified-video-player {
  position: relative;
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
}

.video-container {
  position: relative;
  width: 100%;
  padding-bottom: 177.78%; /* 9:16 aspect ratio */
  background: #000;
  border-radius: 8px;
  overflow: hidden;
}

.video-container iframe,
.video-container blockquote {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border: none;
}

.video-error {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #999;
  font-size: 16px;
}

/* Адаптивность */
@media (max-width: 768px) {
  .video-container {
    padding-bottom: 177.78%; /* Сохраняем 9:16 на мобильных */
  }
}
```

### 6.3. Компонент YouTubePlayer

**Файл:** `src/components/VideoPlayer/YouTubePlayer.jsx`

```jsx
import React, { useEffect, useRef } from 'react';

const YouTubePlayer = ({ videoId, autoplay, muted, loop }) => {
  const containerRef = useRef(null);

  useEffect(() => {
    // Параметры для YouTube iframe
    const params = new URLSearchParams({
      controls: '0',
      autoplay: autoplay ? '1' : '0',
      mute: muted ? '1' : '0',
      rel: '0',
      playsinline: '1',
      enablejsapi: '1',
      loop: loop ? '1' : '0',
      playlist: loop ? videoId : undefined, // Для loop нужен playlist
    });

    // Удалить undefined значения
    params.delete('playlist');

    const embedUrl = `https://www.youtube.com/embed/${videoId}?${params.toString()}`;

    // Создать iframe
    if (containerRef.current) {
      const iframe = document.createElement('iframe');
      iframe.src = embedUrl;
      iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
      iframe.allowFullscreen = true;
      iframe.style.width = '100%';
      iframe.style.height = '100%';
      iframe.style.border = 'none';

      containerRef.current.innerHTML = '';
      containerRef.current.appendChild(iframe);
    }
  }, [videoId, autoplay, muted, loop]);

  return <div ref={containerRef} className="youtube-player" />;
};

export default YouTubePlayer;
```

### 6.4. Компонент TikTokPlayer

**Файл:** `src/components/VideoPlayer/TikTokPlayer.jsx`

```jsx
import React, { useEffect, useRef } from 'react';

const TikTokPlayer = ({ videoId, autoplay, muted, loop }) => {
  const containerRef = useRef(null);

  useEffect(() => {
    // Параметры для TikTok iframe
    const params = new URLSearchParams({
      controls: '0',
      autoplay: autoplay ? '1' : '0',
      muted: muted ? '1' : '0',
      loop: loop ? '1' : '0',
      rel: '0',
    });

    const embedUrl = `https://www.tiktok.com/player/v1/${videoId}?${params.toString()}`;

    // Создать iframe
    if (containerRef.current) {
      const iframe = document.createElement('iframe');
      iframe.src = embedUrl;
      iframe.allow = 'autoplay; fullscreen';
      iframe.allowFullscreen = true;
      iframe.style.width = '100%';
      iframe.style.height = '100%';
      iframe.style.border = 'none';

      containerRef.current.innerHTML = '';
      containerRef.current.appendChild(iframe);
    }
  }, [videoId, autoplay, muted, loop]);

  return <div ref={containerRef} className="tiktok-player" />;
};

export default TikTokPlayer;
```

### 6.5. Компонент InstagramPlayer

**Файл:** `src/components/VideoPlayer/InstagramPlayer.jsx`

```jsx
import React, { useEffect, useRef } from 'react';

const InstagramPlayer = ({ postId, sourceUrl }) => {
  const containerRef = useRef(null);

  useEffect(() => {
    // Instagram использует embed.js
    if (containerRef.current && sourceUrl) {
      // Создать blockquote для Instagram embed
      const blockquote = document.createElement('blockquote');
      blockquote.className = 'instagram-media';
      blockquote.setAttribute('data-instgrm-permalink', sourceUrl);
      blockquote.setAttribute('data-instgrm-version', '14');

      containerRef.current.innerHTML = '';
      containerRef.current.appendChild(blockquote);

      // Загрузить embed.js скрипт
      if (!document.querySelector('script[src*="instagram.com/embed.js"]')) {
        const script = document.createElement('script');
        script.src = 'https://www.instagram.com/embed.js';
        script.async = true;
        document.body.appendChild(script);
      } else {
        // Если скрипт уже загружен, вызвать обработку
        if (window.instgrm) {
          window.instgrm.Embeds.process();
        }
      }
    }
  }, [postId, sourceUrl]);

  return <div ref={containerRef} className="instagram-player" />;
};

export default InstagramPlayer;
```

### 6.6. Компонент VideoOverlay

**Файл:** `src/components/VideoPlayer/VideoOverlay.jsx`

```jsx
import React, { useState } from 'react';
import './VideoOverlay.css';

const VideoOverlay = ({ platform }) => {
  const [isPlaying, setIsPlaying] = useState(false);
  const [isMuted, setIsMuted] = useState(true);

  const handlePlay = () => {
    setIsPlaying(true);
    // Логика запуска через API платформы
  };

  const handlePause = () => {
    setIsPlaying(false);
    // Логика паузы через API платформы
  };

  const handleMute = () => {
    setIsMuted(true);
    // Логика выключения звука
  };

  const handleUnmute = () => {
    setIsMuted(false);
    // Логика включения звука
  };

  return (
    <div className="video-overlay">
      {!isPlaying && (
        <button className="play-button" onClick={handlePlay}>
          ▶
        </button>
      )}
      {isPlaying && (
        <button className="pause-button" onClick={handlePause}>
          ⏸
        </button>
      )}
      <button 
        className="mute-button" 
        onClick={isMuted ? handleUnmute : handleMute}
      >
        {isMuted ? '🔇' : '🔊'}
      </button>
    </div>
  );
};

export default VideoOverlay;
```

**Стили:** `src/components/VideoPlayer/VideoOverlay.css`

```css
.video-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  z-index: 10;
}

.play-button,
.pause-button {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  pointer-events: auto;
  transition: all 0.2s;
}

.play-button:hover,
.pause-button:hover {
  background: rgba(0, 0, 0, 0.8);
  transform: scale(1.1);
}

.mute-button {
  position: absolute;
  bottom: 20px;
  right: 20px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.6);
  border: none;
  color: white;
  font-size: 20px;
  cursor: pointer;
  pointer-events: auto;
  transition: all 0.2s;
}

.mute-button:hover {
  background: rgba(0, 0, 0, 0.8);
}
```

### 6.7. Интеграция в страницу детального просмотра

**Обновление:** `src/pages/VideoDetail.jsx` (если существует) или создание нового

```jsx
import React from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import UnifiedVideoPlayer from '../components/VideoPlayer/UnifiedVideoPlayer';
import VideoDetailSidebar from '../components/VideoDetailSidebar/VideoDetailSidebar';
import { getVideoReference } from '../services/api';
import './VideoDetail.css';

const VideoDetail = () => {
  const { id } = useParams();
  
  const { data: videoData, isLoading } = useQuery({
    queryKey: ['videoReference', id],
    queryFn: () => getVideoReference(id),
  });

  if (isLoading) {
    return <div>Loading...</div>;
  }

  const video = videoData?.data;

  if (!video) {
    return <div>Video not found</div>;
  }

  return (
    <div className="video-detail-page">
      <div className="video-player-section">
        <UnifiedVideoPlayer
          platform={video.platform}
          platformVideoId={video.platform_video_id}
          sourceUrl={video.source_url}
          autoplay={true}
          muted={true}
        />
      </div>
      <VideoDetailSidebar video={video} />
    </div>
  );
};

export default VideoDetail;
```

---

## 7. Интеграция и тестирование

### 7.1. План тестирования

#### 7.1.1. Backend тесты

**Unit тесты для PlatformNormalizationService:**
- Тест определения YouTube платформы
- Тест определения TikTok платформы
- Тест определения Instagram платформы
- Тест извлечения YouTube ID из разных форматов
- Тест извлечения TikTok ID
- Тест извлечения Instagram ID
- Тест обработки невалидных URL

**Integration тесты:**
- Тест создания video reference с нормализацией
- Тест обновления video reference с новым URL
- Тест API response с нормализованными данными

#### 7.1.2. Frontend тесты

**Компонентные тесты:**
- Тест рендеринга UnifiedVideoPlayer для каждой платформы
- Тест переключения между платформами
- Тест обработки ошибок (невалидный platform, отсутствие video_id)

**E2E тесты:**
- Тест открытия видео из списка
- Тест воспроизведения YouTube видео
- Тест воспроизведения TikTok видео
- Тест воспроизведения Instagram видео
- Тест работы overlay кнопок

### 7.2. Проверочный список

**Backend:**
- [ ] Миграция добавлена и выполнена
- [ ] PlatformNormalizationService создан и протестирован
- [ ] VideoReferenceController обновлен
- [ ] API возвращает platform_video_id
- [ ] Валидация URL работает корректно

**Frontend:**
- [ ] UnifiedVideoPlayer компонент создан
- [ ] YouTubePlayer компонент создан
- [ ] TikTokPlayer компонент создан
- [ ] InstagramPlayer компонент создан
- [ ] VideoOverlay компонент создан
- [ ] Страница детального просмотра обновлена
- [ ] Стили применены корректно
- [ ] Адаптивность работает на мобильных

**Интеграция:**
- [ ] Видео из YouTube воспроизводится
- [ ] Видео из TikTok воспроизводится
- [ ] Видео из Instagram отображается
- [ ] Overlay кнопки работают
- [ ] Автоплей работает (muted)
- [ ] Остановка при скролле работает

---

## 8. Расширяемость и будущие улучшения

### 8.1. Добавление новых платформ

**Процесс:**
1. Добавить определение платформы в `PlatformNormalizationService::detectPlatform()`
2. Добавить метод извлечения ID (например, `extractVimeoId()`)
3. Добавить case в `normalizeUrl()`
4. Создать компонент плеера на frontend (например, `VimeoPlayer.jsx`)
5. Добавить case в `UnifiedVideoPlayer::renderPlayer()`

### 8.2. Улучшения API управления

**Планируемые функции:**
- Программное управление через YouTube IFrame API
- Программное управление через TikTok postMessage API
- Синхронизация состояния между overlay и реальным плеером
- Обработка событий (play, pause, ended) от платформ

### 8.3. Оптимизация производительности

**Возможные улучшения:**
- Lazy loading iframe (загрузка только при видимости)
- Preload следующего видео
- Кэширование embed кодов
- Оптимизация размеров iframe

### 8.4. Улучшение Instagram интеграции

**Будущие шаги:**
- Интеграция с Instagram Graph API (требует токена)
- Получение прямого URL видео (если доступно)
- Кастомизация embed (если API позволит)

---

## Заключение

Эта архитектура обеспечивает:
- ✅ Единый пользовательский опыт для всех платформ
- ✅ Чистое разделение ответственности (Backend/Frontend)
- ✅ Легкую расширяемость для новых платформ
- ✅ Использование официальных методов встраивания
- ✅ Гибкость в настройке и кастомизации

Реализация должна быть поэтапной:
1. **Этап 1:** Backend нормализация (YouTube, TikTok)
2. **Этап 2:** Frontend базовый плеер (YouTube, TikTok)
3. **Этап 3:** Overlay и управление
4. **Этап 4:** Instagram интеграция
5. **Этап 5:** Оптимизация и улучшения

