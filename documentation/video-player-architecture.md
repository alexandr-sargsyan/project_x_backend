# Архитектура единой системы воспроизведения видео
## Filmmaker Reference Platform - Unified Video Player

**Версия:** 1.0  
**Дата:** 2026-01-09  
**Последнее обновление:** 2026-01-09  
**Статус:** ✅ Реализовано

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

| Платформа | Статус | Метод встраивания | Управление |
|-----------|--------|-------------------|------------|
| YouTube | ✅ Полная поддержка | iframe embed | ❌ Нет управления (только воспроизведение) |
| YouTube Shorts | ✅ Полная поддержка | iframe embed (тот же, что YouTube) | ❌ Нет управления (только воспроизведение) |
| TikTok | ✅ Полная поддержка | iframe embed/v2 | ❌ Нет управления (только воспроизведение) |
| Instagram | ⚠️ Базовая поддержка | embed.js (стандартный embed) | ❌ Нет программного управления |

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
│  │  - Простое воспроизведение без управления          │  │
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
```

**Примечание:** Все плееры используют простые iframe без программного управления. Видео воспроизводится автоматически (muted) без возможности управления через UI.

### 4.2. Единые параметры воспроизведения

**Для всех платформ:**
- Пропорции: 9:16 (вертикальные видео) или 16:9 (горизонтальные)
- Автоплей: включен (muted)
- Контролы платформы: скрыты
- Звук: по умолчанию выключен
- Управление: отсутствует (только простое воспроизведение)

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
- `enablejsapi=1` - включить JavaScript API (не используется для управления)

**Управление:**
- ❌ Нет программного управления
- Простое воспроизведение через iframe

#### 4.3.2. TikTok

**iframe URL:**
```
https://www.tiktok.com/embed/v2/{VIDEO_ID}?controls=0&autoplay=1&muted=1&loop=1&rel=0
```

**Параметры:**
- `controls=0` - скрыть встроенные контролы
- `autoplay=1` - автозапуск
- `muted=1` - без звука
- `loop=1` - зацикливание
- `rel=0` - не показывать рекомендации

**Управление:**
- ❌ Нет программного управления
- Простое воспроизведение через iframe

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

**Статус:** ✅ Реализовано

#### 5.1.1. Структура сервиса

**Файл:** `app/Services/PlatformNormalizationService.php`

**Реализованные методы:**
- ✅ `normalizeUrl(string $url): array` - главный метод нормализации
- ✅ `detectPlatform(string $url): ?string` - определение платформы
- ✅ `extractYouTubeId(string $url): ?string` - извлечение YouTube ID
- ✅ `extractTikTokId(string $url): ?string` - извлечение TikTok ID
- ✅ `extractInstagramId(string $url): ?string` - извлечение Instagram ID
- ✅ `resolveShortUrl(string $url): ?string` - разрешение коротких ссылок (TikTok)

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

**Статус:** ✅ Реализовано

#### 5.2.1. Обновление StoreVideoReferenceRequest

**Валидация source_url:**
```php
'source_url' => ['required', 'url', 'max:2048'],
```

#### 5.2.2. Обновление VideoReferenceController

**Реализовано:**
- ✅ Инъекция `PlatformNormalizationService` в конструктор
- ✅ Нормализация URL в методе `store()`
- ✅ Нормализация URL в методе `update()` при изменении `source_url`
- ✅ Fallback на `PlatformEnum::fromUrl()` если нормализация не удалась

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

**Статус:** ✅ Реализовано и выполнено

**Файл:** `database/migrations/2026_01_09_202621_add_platform_video_id_to_video_references_table.php`

**Добавлено поле:**
- `platform_video_id VARCHAR(255) NULL` - после поля `platform`

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

**Статус:** ✅ Реализовано

**Добавлено:**
- ✅ `platform_video_id` в `$fillable`
- ✅ `platform_video_id` в `$casts` как `string`

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

**Статус:** ✅ Реализовано (упрощенная версия)

**Особенности реализации:**
- Простой компонент-роутер для выбора нужного плеера
- Не использует хуки управления состоянием
- Не использует refs для управления
- Просто передает пропсы в соответствующий платформенный плеер

**Логика:**
- Определяет платформу через `switch` statement
- Рендерит соответствующий компонент (`YouTubePlayer`, `TikTokPlayer`, `InstagramPlayer`)
- Оборачивает в единый контейнер с общими стилями

**Пропсы:**
- `platform` - платформа видео ('youtube', 'tiktok', 'instagram')
- `platformVideoId` - ID видео на платформе
- `sourceUrl` - оригинальный URL (для Instagram)
- `autoplay` - автозапуск (по умолчанию `true`)
- `muted` - без звука (по умолчанию `true`)
- `loop` - зацикливание (по умолчанию `false`)

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

**Статус:** ✅ Реализовано (упрощенная версия)

**Особенности реализации:**
- Простой iframe embed без использования YouTube IFrame Player API
- Создание iframe с параметрами через URL
- Автоплей с выключенным звуком
- Скрытые контролы платформы

**Параметры iframe:**
- `controls=0` - скрыть контролы
- `autoplay=1` - автозапуск
- `mute=1` - без звука
- `rel=0` - не показывать связанные видео
- `playsinline=1` - воспроизведение на мобильных
- `enablejsapi=1` - включить JS API (не используется)

**Управление:**
- ❌ Нет программного управления
- Только простое воспроизведение

### 6.4. Компонент TikTokPlayer

**Файл:** `src/components/VideoPlayer/TikTokPlayer.jsx`

**Статус:** ✅ Реализовано (упрощенная версия)

**Особенности реализации:**
- Простой iframe embed
- Использует `https://www.tiktok.com/embed/v2/{VIDEO_ID}`
- Параметры передаются через URL (autoplay, muted, loop)
- Создание iframe с настройками

**Параметры iframe:**
- `controls=0` - скрыть контролы
- `autoplay=1` - автозапуск
- `muted=1` - без звука
- `loop=1` - зацикливание
- `rel=0` - не показывать рекомендации

**Управление:**
- ❌ Нет программного управления
- Только простое воспроизведение

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

### 6.6. Управление воспроизведением

**Статус:** ❌ Не реализовано

**Текущее состояние:**
- Нет компонента VideoOverlay
- Нет хука useVideoPlayer
- Нет методов управления (play/pause/mute/unmute)
- Видео воспроизводится автоматически без возможности управления

**Примечание:**
Логика управления была удалена для упрощения. Видео воспроизводится автоматически (muted) без UI элементов управления.

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
- [x] Миграция добавлена и выполнена
- [x] PlatformNormalizationService создан
- [x] VideoReferenceController обновлен
- [x] API возвращает platform_video_id
- [x] Валидация URL работает корректно
- [ ] Unit тесты для PlatformNormalizationService (планируется)

**Frontend:**
- [x] UnifiedVideoPlayer компонент создан (упрощенная версия)
- [x] YouTubePlayer компонент создан (простой iframe)
- [x] TikTokPlayer компонент создан (простой iframe)
- [x] InstagramPlayer компонент создан
- [ ] VideoOverlay компонент - удален
- [ ] useVideoPlayer хук - не используется
- [x] Страница детального просмотра обновлена
- [x] Стили применены корректно
- [x] Адаптивность работает на мобильных

**Интеграция:**
- [x] Видео из YouTube воспроизводится
- [x] Видео из TikTok воспроизводится
- [x] Видео из Instagram отображается
- [x] Автоплей работает (muted)
- [ ] Управление воспроизведением - не реализовано
- [ ] Остановка при скролле - не реализовано

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

**Текущее состояние:**
- ❌ Нет программного управления
- ❌ Нет overlay с кнопками
- ❌ Нет обработки событий от плееров

**Планируется (опционально):**
- [ ] Добавление VideoOverlay с кнопками Play/Pause
- [ ] Добавление управления звуком
- [ ] Интеграция YouTube IFrame Player API для управления
- [ ] Обработка событий (play, pause, ended) от платформ

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

**Статус реализации:**

1. ✅ **Этап 1:** Backend нормализация (YouTube, TikTok, Instagram) - **Завершено**
2. ✅ **Этап 2:** Frontend базовый плеер (YouTube, TikTok, Instagram) - **Завершено**
3. ❌ **Этап 3:** Overlay и управление - **Удалено** (логика управления удалена для упрощения)
4. ⚠️ **Этап 4:** Instagram интеграция - **Базовая поддержка** (нет программного управления)
5. 🔄 **Этап 5:** Оптимизация и улучшения - **В процессе**

**Текущее состояние:**
- ✅ Простое воспроизведение видео через iframe
- ✅ Автоплей с выключенным звуком
- ✅ Скрытые контролы платформ
- ❌ Нет UI элементов управления (кнопки Play/Pause/Mute)
- ❌ Нет программного управления воспроизведением
- ❌ Остановка при скролле не реализована

**Известные ограничения:**
- Нет управления воспроизведением (удалено для упрощения)
- Instagram: Нет программного управления (использует стандартный embed)
- Остановка при скролле: Не реализовано

**Следующие шаги (опционально):**
- Добавление управления воспроизведением (если потребуется)
- Реализация остановки видео при скролле
- Добавление unit тестов для PlatformNormalizationService
- Оптимизация производительности (lazy loading, preload)

