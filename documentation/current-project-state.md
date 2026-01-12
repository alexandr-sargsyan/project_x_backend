# 📊 Текущее состояние проекта: Filmmaker Reference Platform

**Дата обновления:** 2026-01-12  
**Версия:** MVP (в разработке)

---

## 🎯 Обзор проекта

Filmmaker Reference Platform — это платформа для поиска и каталогизации видео-референсов для видеографов, монтажёров и режиссёров. Платформа поддерживает видео с 4 платформ: **YouTube**, **TikTok**, **Instagram** и **Facebook**.

### Технологический стек

- **Backend:** Laravel 12, PHP 8.4+
- **База данных:** PostgreSQL 12+ (full-text search через tsvector/tsquery)
- **Frontend:** React 19, React Router DOM 7, TanStack Query 5
- **Admin Panel:** React 19, React Router DOM 7

---

## 🗄️ Структура базы данных

### Таблицы

#### 1. `categories`
Категории видео-референсов (иерархическая структура).

**Поля:**
- `id` (bigint, PK)
- `name` (string, unique)
- `slug` (string, unique)
- `parent_id` (bigint, nullable, FK → categories.id) — для подкатегорий
- `order` (integer, default 0)
- `created_at`, `updated_at` (timestamps)

#### 2. `video_references`
Основная таблица для видео-референсов.

**Display Fields (что видит пользователь):**
- `id` (bigint, PK)
- `title` (string) — заголовок
- `source_url` (string) — оригинальная ссылка на видео
- `preview_url` (string, nullable) — URL превью изображения
- `preview_embed` (text, nullable) — embed код
- `public_summary` (text, nullable) — короткое описание
- `details_public` (json, nullable) — дополнительные детали
- `duration_sec` (integer, nullable) — длительность в секундах

**Filter Fields (по чему фильтруем):**
- `category_id` (bigint, FK → categories.id)
- `platform` (string, nullable) — платформа: `youtube`, `tiktok`, `instagram`, `facebook`
- `platform_video_id` (string, nullable) — ID видео на платформе после нормализации
- `pacing` (string, nullable) — темп: `slow`, `fast`, `mixed`
- `hook_type` (string, nullable) — тип "хука"
- `production_level` (string, nullable) — уровень продакшена: `low`, `mid`, `high`
- `has_visual_effects` (boolean, default false)
- `has_3d` (boolean, default false)
- `has_animations` (boolean, default false)
- `has_typography` (boolean, default false)
- `has_sound_design` (boolean, default false)

**Search Fields (что индексируется для поиска):**
- `search_profile` (text) — ключевая идея, структурированное описание
- `search_metadata` (text, nullable) — синонимы, ключевые слова
- `search_vector` (tsvector, computed column) — автоматически генерируется из `title`, `search_profile`, `search_metadata`
- `search_vector_idx` (GIN index) — индекс для быстрого full-text search

**Ранжирование:**
- `quality_score` (integer, default 0) — автоматически рассчитывается при сохранении
- `completeness_flags` (json, nullable) — автоматически рассчитывается при сохранении

**Служебные:**
- `created_at`, `updated_at` (timestamps)

#### 3. `tags`
Теги для видео-референсов.

**Поля:**
- `id` (bigint, PK)
- `name` (string, unique)
- `created_at`, `updated_at` (timestamps)

#### 4. `video_reference_tag`
Pivot таблица для связи многие-ко-многим между `video_references` и `tags`.

**Поля:**
- `video_reference_id` (bigint, FK → video_references.id)
- `tag_id` (bigint, FK → tags.id)
- Уникальный индекс на `(video_reference_id, tag_id)`

#### 5. `tutorials`
Обучающие материалы (могут быть связаны с несколькими видео).

**Поля:**
- `id` (bigint, PK)
- `tutorial_url` (string, nullable) — ссылка на внешний урок
- `label` (string, nullable) — название сегмента/урока
- `created_at`, `updated_at` (timestamps)

**Валидация:** Хотя бы одно из полей (`tutorial_url` или `label`) должно быть заполнено.

#### 6. `tutorial_video_reference`
Pivot таблица для связи многие-ко-многим между `tutorials` и `video_references`.

**Поля:**
- `id` (bigint, PK)
- `tutorial_id` (bigint, FK → tutorials.id)
- `video_reference_id` (bigint, FK → video_references.id)
- `start_sec` (integer, nullable) — начало сегмента в секундах
- `end_sec` (integer, nullable) — конец сегмента в секундах
- `created_at`, `updated_at` (timestamps)
- Уникальный индекс на `(tutorial_id, video_reference_id)`

---

## 🔧 Backend (Laravel)

### Модели

#### `VideoReference`
**Расположение:** `app/Models/VideoReference.php`

**Связи:**
- `category()` — BelongsTo → Category
- `tags()` — BelongsToMany → Tag (через `video_reference_tag`)
- `tutorials()` — BelongsToMany → Tutorial (через `tutorial_video_reference`, с pivot полями `start_sec`, `end_sec`)

**Computed Attributes:**
- `tags_text` — склеенные теги в строку для поиска
- `has_tutorial` — проверка наличия tutorials
- `embed_url` — URL для встраивания (зависит от платформы)

**Scopes:**
- `scopeSearch()` — full-text search через PostgreSQL tsvector
- `scopeFilterByCategory()` — фильтрация по категории
- `scopeFilterByPlatform()` — фильтрация по платформе
- `scopeFilterByPacing()` — фильтрация по темпу
- `scopeFilterByProductionLevel()` — фильтрация по уровню продакшена
- `scopeFilterByHasFlags()` — фильтрация по has_* полям

**Автоматические расчёты:**
- `quality_score` — рассчитывается при сохранении (saving event)
- `completeness_flags` — рассчитывается при сохранении (saving event)

#### `Tutorial`
**Расположение:** `app/Models/Tutorial.php`

**Связи:**
- `videoReferences()` — BelongsToMany → VideoReference (через `tutorial_video_reference`, с pivot полями `start_sec`, `end_sec`)

**Валидация:**
- При сохранении проверяется, что хотя бы одно из полей (`tutorial_url` или `label`) заполнено.

#### `Category`
**Расположение:** `app/Models/Category.php`

**Связи:**
- `videoReferences()` — HasMany → VideoReference

#### `Tag`
**Расположение:** `app/Models/Tag.php`

**Связи:**
- `videoReferences()` — BelongsToMany → VideoReference (через `video_reference_tag`)

### Enums

#### `PlatformEnum`
**Расположение:** `app/Enums/PlatformEnum.php`

**Значения:**
- `INSTAGRAM = 'instagram'`
- `TIKTOK = 'tiktok'`
- `YOUTUBE = 'youtube'`
- `FACEBOOK = 'facebook'`

**Методы:**
- `values()` — получить все значения
- `fromUrl(string $url)` — определить платформу по URL

#### `PacingEnum`
**Расположение:** `app/Enums/PacingEnum.php`

**Значения:**
- `SLOW = 'slow'`
- `FAST = 'fast'`
- `MIXED = 'mixed'`

#### `ProductionLevelEnum`
**Расположение:** `app/Enums/ProductionLevelEnum.php`

**Значения:**
- `LOW = 'low'`
- `MID = 'mid'`
- `HIGH = 'high'`

### Сервисы

#### `PlatformNormalizationService`
**Расположение:** `app/Services/PlatformNormalizationService.php`

**Назначение:** Нормализация URL видео и извлечение platform и platform_video_id.

**Методы:**
- `normalizeUrl(string $url): array` — нормализует URL и возвращает `['platform' => string|null, 'platform_video_id' => string|null, 'normalized' => bool]`

**Поддерживаемые форматы URL:**

**YouTube:**
- `youtube.com/watch?v={ID}`
- `youtu.be/{ID}`
- `youtube.com/shorts/{ID}`
- `youtube.com/embed/{ID}`
- `m.youtube.com/watch?v={ID}`

**TikTok:**
- `tiktok.com/@username/video/{ID}`
- `vm.tiktok.com` (с разрешением редиректа)
- `t.tiktok.com` (с разрешением редиректа)
- `m.tiktok.com/v/{ID}`

**Instagram:**
- `instagram.com/p/{ID}`
- `instagram.com/reel/{ID}`
- `instagram.com/tv/{ID}`

**Facebook:**
- `facebook.com/reel/{ID}`
- `facebook.com/watch/?v={ID}`
- `facebook.com/{user}/videos/{ID}/`
- `facebook.com/{user}/posts/{ID}`

#### `PostgresSearchService`
**Расположение:** `app/Services/PostgresSearchService.php`

**Назначение:** Выполнение поиска и фильтрации через PostgreSQL.

**Методы:**
- `search(?string $searchTerm, array $filters, int $perPage, int $page): LengthAwarePaginator`

**Фильтры:**
- `category_id` — может быть массивом (множественный выбор)
- `platform` — может быть массивом (множественный выбор через `whereIn`)
- `pacing` — строка
- `production_level` — строка
- `has_visual_effects`, `has_3d`, `has_animations`, `has_typography`, `has_sound_design`, `has_tutorial` — boolean
- `tag_ids` — массив ID тегов

**Full-text Search:**
- Использует `search_vector @@ to_tsquery('russian', ?)`
- Ищет по полям: `title`, `search_profile`, `search_metadata`
- Использует GIN индекс для быстрого поиска

### Контроллеры

#### `VideoReferenceController`
**Расположение:** `app/Http/Controllers/VideoReferenceController.php`

**Методы:**
- `index(FilterVideoReferenceRequest $request)` — GET `/api/video-references` — список с поиском и фильтрацией
- `show(int $id)` — GET `/api/video-references/{id}` — детальная информация
- `store(StoreVideoReferenceRequest $request)` — POST `/api/video-references` — создание
- `update(UpdateVideoReferenceRequest $request, int $id)` — PUT `/api/video-references/{id}` — обновление
- `destroy(int $id)` — DELETE `/api/video-references/{id}` — удаление

**Особенности:**
- Автоматическая нормализация URL при создании/обновлении через `PlatformNormalizationService`
- Автоматическое создание тегов по именам (case-insensitive поиск существующих)
- Поддержка many-to-many связи с tutorials (режимы "new" и "select")
- При обновлении всегда синхронизирует tutorials (даже если пустой массив — удаляет все связи)

#### `CategoryController`
**Расположение:** `app/Http/Controllers/CategoryController.php`

**Методы:**
- `index()` — GET `/api/categories` — список всех категорий
- `show(int $id)` — GET `/api/categories/{id}` — детальная информация
- `store(StoreCategoryRequest $request)` — POST `/api/categories` — создание
- `update(UpdateCategoryRequest $request, int $id)` — PUT `/api/categories/{id}` — обновление
- `destroy(int $id)` — DELETE `/api/categories/{id}` — удаление

#### `TagController`
**Расположение:** `app/Http/Controllers/TagController.php`

**Методы:**
- `index(Request $request)` — GET `/api/tags` — список тегов с поиском (query параметр `search`)

#### `TutorialController`
**Расположение:** `app/Http/Controllers/TutorialController.php`

**Методы:**
- `index()` — GET `/api/tutorials` — список всех tutorials (id, label, tutorial_url)

### Request Validation

#### `FilterVideoReferenceRequest`
**Расположение:** `app/Http/Requests/FilterVideoReferenceRequest.php`

**Параметры:**
- `search` (nullable, string)
- `category_id` (nullable, может быть массивом)
- `platform` (nullable, может быть массивом)
- `pacing` (nullable, string, Rule::in(PacingEnum::values()))
- `production_level` (nullable, string, Rule::in(ProductionLevelEnum::values()))
- `has_visual_effects`, `has_3d`, `has_animations`, `has_typography`, `has_sound_design`, `has_tutorial` (nullable, boolean)
- `tag_ids` (nullable, array)
- `page` (nullable, integer, min:1)
- `per_page` (nullable, integer, min:1, max:100)

#### `StoreVideoReferenceRequest` / `UpdateVideoReferenceRequest`
**Расположение:** `app/Http/Requests/StoreVideoReferenceRequest.php`, `app/Http/Requests/UpdateVideoReferenceRequest.php`

**Параметры:**
- `title` (required, string, max:255)
- `source_url` (required, url, max:2048)
- `category_id` (required, integer, exists:categories,id)
- `search_profile` (required, string)
- `search_metadata` (nullable, string)
- `preview_url` (nullable, url)
- `preview_embed` (nullable, string)
- `public_summary` (nullable, string)
- `details_public` (nullable, json)
- `duration_sec` (nullable, integer, min:0)
- `platform` (nullable, string, Rule::in(PlatformEnum::values()))
- `pacing` (nullable, string, Rule::in(PacingEnum::values()))
- `production_level` (nullable, string, Rule::in(ProductionLevelEnum::values()))
- `hook_type` (nullable, string)
- `has_visual_effects`, `has_3d`, `has_animations`, `has_typography`, `has_sound_design` (nullable, boolean)
- `tags` (nullable, array) — массив имен тегов
- `tutorials` (nullable, array) — массив объектов tutorial:
  - `mode` (required, 'new' | 'select')
  - `tutorial_id` (required if mode='select', integer, exists:tutorials,id)
  - `tutorial_url` (required if mode='new', url, max:2048)
  - `label` (required if mode='new', string, max:255)
  - `start_sec` (nullable, integer, min:0)
  - `end_sec` (nullable, integer, min:0)

---

## 🎨 Frontend (React)

### Структура компонентов

#### Страницы

**`Home.jsx`**
- Главная страница с каталогом видео
- Интегрирует `VideoGrid`, `SearchBar`, `CategorySidebar`, `FilterSidebar`
- Управляет состоянием фильтров и поиска
- Использует TanStack Query для загрузки данных

**`VideoDetail.jsx`**
- Детальная страница видео
- Интегрирует `VideoDetailView` и `VideoDetailSidebar`

#### Компоненты видео-плееров

**`usePlatformPlayer.js`**
- Хук для выбора и рендеринга правильного плеера
- Поддерживает: YouTube, TikTok, Instagram, Facebook
- Возвращает функцию `renderPlayer(playerProps)`

**`VideoListPlayer.jsx`**
- Компонент для отображения видео в списке
- Параметры по умолчанию: `autoplay={isVisible}`, `muted={true}`, `loop={true}`, `controls={false}`
- Lazy loading через Intersection Observer

**`VideoDetailPlayer.jsx`**
- Компонент для отображения видео на детальной странице
- Параметры по умолчанию: `autoplay={true}`, `muted={false}`, `loop={false}`, `controls={true}`

**`YouTubePlayer.jsx`**
- Iframe с YouTube Embed API
- Поддерживает: `autoplay`, `muted`, `loop`, `controls`

**`TikTokPlayer.jsx`**
- Iframe с TikTok Player v1 API
- Поддерживает: `autoplay`, `muted`, `loop`, `controls`

**`InstagramPlayer.jsx`**
- Использует официальный Instagram Embed.js
- Создает `<blockquote>` элемент с классом `instagram-media`
- Нормализует URL (убирает query параметры)
- Добавляет `data-instgrm-captioned="true"` для inline playback
- Не поддерживает программное управление параметрами

**`FacebookPlayer.jsx`**
- Использует официальный Facebook Video Plugin через iframe
- Endpoint: `https://www.facebook.com/plugins/video.php`
- Поддерживает: Reels, Watch, обычные видео, посты с видео
- Нормализует URL (убирает query параметры, кроме `/watch/?v=`)
- Параметры: `showText` (boolean)

#### Компоненты UI

**`VideoCard.jsx`**
- Карточка видео в списке
- Lazy loading через Intersection Observer
- Приоритет: активное видео → preview_url → placeholder

**`VideoGrid.jsx`**
- Сетка видео-карточек
- Responsive layout

**`VideoDetailView.jsx`**
- Детальный вид видео
- Отображает всю информацию о видео

**`VideoDetailSidebar.jsx`**
- Боковая панель с деталями видео
- Отображает категорию, теги, tutorials, флаги

**`FilterSidebar.jsx`**
- Боковая панель с фильтрами
- **Platform:** чекбоксы (множественный выбор) — YouTube, Instagram, TikTok, Facebook
- **Pacing:** селектор — Any, Slow, Fast, Mixed
- **Production Level:** селектор — Any, Low, Mid, High
- **Tags:** поиск с множественным выбором
- **Checkboxes:** Visual Effects, 3D, Animations, Typography, Sound Design, Has Tutorial
- Кнопка "Reset Filters"

**`CategorySidebar.jsx`**
- Боковая панель с категориями
- Множественный выбор категорий

**`SearchBar.jsx`**
- Поисковая строка
- Debounce для оптимизации запросов

**`TutorialCard.jsx`**
- Карточка tutorial
- Отображает label, tutorial_url, start_sec, end_sec

**`TagBadge.jsx`**
- Бейдж тега

### API Service

**`api.js`**
- `searchVideoReferences(query, filters)` — поиск с фильтрами
- `getVideoReference(id)` — получить видео по ID
- `getCategories()` — список категорий
- `getTags(search)` — список тегов с поиском
- `tutorialsAPI.getAll()` — список всех tutorials

---

## 🛠️ Admin Panel (React)

### Структура компонентов

#### Страницы

**`VideoReferences.jsx`**
- Список всех видео-референсов
- Интегрирует `VideoReferenceList`

**`Categories.jsx`**
- Управление категориями
- Интегрирует `CategoryList` и `CategoryForm`

### Компоненты

**`VideoReferenceList.jsx`**
- Таблица со списком видео-референсов
- Кнопки: Create, Edit, Delete

**`VideoReferenceForm.jsx`**
- Форма создания/редактирования видео-референса
- Все поля из структуры данных
- **Tutorials:**
  - Переключатель режима "New" / "Select" для каждого tutorial
  - В режиме "New": обязательные поля `tutorial_url` и `label`
  - В режиме "Select": выбор из существующих tutorials (по label)
  - Поля `start_sec` и `end_sec` доступны в обоих режимах
  - Всегда отправляет поле `tutorials` (даже если пустой массив) для корректной синхронизации

**`CategoryList.jsx`**
- Список категорий
- Кнопки: Create, Edit, Delete

**`CategoryForm.jsx`**
- Форма создания/редактирования категории

**`Sidebar.jsx`**
- Боковая навигация админ-панели

**`ConfirmModal.jsx`**
- Модальное окно подтверждения удаления

### API Service

**`api.js`**
- CRUD операции для video-references, categories
- `tutorialsAPI.getAll()` — список всех tutorials для селектора

---

## 🔍 Поиск и фильтрация

### Full-text Search (PostgreSQL)

**Механизм:**
- Использует `tsvector` (computed column) на полях: `title`, `search_profile`, `search_metadata`
- GIN индекс для быстрого поиска
- Язык: `russian`

**Запрос:**
```sql
WHERE search_vector @@ to_tsquery('russian', ?)
```

### Фильтры

**Поддерживаемые фильтры:**
- `category_id` — массив (множественный выбор)
- `platform` — массив (множественный выбор) — YouTube, Instagram, TikTok, Facebook
- `pacing` — строка — slow, fast, mixed
- `production_level` — строка — low, mid, high
- `has_visual_effects`, `has_3d`, `has_animations`, `has_typography`, `has_sound_design`, `has_tutorial` — boolean
- `tag_ids` — массив ID тегов

**Реализация:**
- Фильтры применяются через `PostgresSearchService`
- `platform` использует `whereIn()` для массива
- `has_tutorial` рассчитывается как `tutorials_count > 0`

---

## 📺 Видео-плееры

### Поддерживаемые платформы

1. **YouTube**
   - URL формат: `https://www.youtube.com/embed/{VIDEO_ID}?params`
   - Параметры: `controls`, `autoplay`, `mute`, `loop`, `rel=0`, `playsinline=1`, `enablejsapi=1`
   - Для loop используется `playlist={VIDEO_ID}`

2. **TikTok**
   - URL формат: `https://www.tiktok.com/player/v1/{VIDEO_ID}?params`
   - Параметры: `autoplay`, `loop`, `muted`, `controls`, `description=0`, `music_info=0`, `rel=0`

3. **Instagram**
   - Использует официальный Instagram Embed.js
   - Создает `<blockquote>` элемент с `data-instgrm-permalink` и `data-instgrm-captioned="true"`
   - Нормализует URL (убирает query параметры)
   - Не поддерживает программное управление параметрами

4. **Facebook**
   - URL формат: `https://www.facebook.com/plugins/video.php?href={ENCODED_URL}&show_text={0|1}&width=400`
   - Поддерживает: Reels, Watch, обычные видео, посты с видео
   - Нормализует URL (убирает query параметры, кроме `/watch/?v=`)
   - Iframe атрибуты: `width="400"`, `height="700"`, `style="border:none;overflow:hidden"`, `scrolling="no"`, `frameborder="0"`, `allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"`, `allowfullscreen="true"`

### Параметры по умолчанию

**VideoListPlayer (список):**
- `autoplay={isVisible}` — только если видно в viewport
- `muted={true}` — без звука
- `loop={true}` — с зацикливанием
- `controls={false}` — без контролов

**VideoDetailPlayer (детальная страница):**
- `autoplay={true}` — всегда автозапуск
- `muted={false}` — со звуком
- `loop={false}` — без зацикливания
- `controls={true}` — с контролами

---

## 🔄 Связи и отношения

### VideoReference ↔ Tutorial (Many-to-Many)

**Pivot таблица:** `tutorial_video_reference`

**Pivot поля:**
- `start_sec` (integer, nullable) — начало сегмента в секундах
- `end_sec` (integer, nullable) — конец сегмента в секундах

**Логика:**
- Один tutorial может быть связан с несколькими video_references
- Один video_reference может иметь несколько tutorials
- Каждая связь может иметь свои `start_sec` и `end_sec`

**Режимы создания:**
- **"New":** создается новый tutorial с обязательными `tutorial_url` и `label`
- **"Select":** выбирается существующий tutorial по ID

**Синхронизация:**
- При обновлении всегда отправляется поле `tutorials` (даже если пустой массив)
- Пустой массив удаляет все связи через `sync([])`

### VideoReference ↔ Tag (Many-to-Many)

**Pivot таблица:** `video_reference_tag`

**Логика:**
- Теги создаются автоматически по именам (case-insensitive поиск существующих)
- Один video_reference может иметь несколько тегов
- Один тег может быть связан с несколькими video_references

### VideoReference ↔ Category (Many-to-One)

**Логика:**
- Один video_reference принадлежит одной категории
- Одна категория может иметь несколько video_references

---

## 🚀 API Endpoints

### Video References

- `GET /api/video-references` — список с поиском и фильтрацией
- `GET /api/video-references/{id}` — детальная информация
- `POST /api/video-references` — создание
- `PUT /api/video-references/{id}` — обновление
- `DELETE /api/video-references/{id}` — удаление

### Categories

- `GET /api/categories` — список всех категорий
- `GET /api/categories/{id}` — детальная информация
- `POST /api/categories` — создание
- `PUT /api/categories/{id}` — обновление
- `DELETE /api/categories/{id}` — удаление

### Tags

- `GET /api/tags?search={query}` — список тегов с поиском

### Tutorials

- `GET /api/tutorials` — список всех tutorials (id, label, tutorial_url)

---

## 📝 Важные особенности

### Автоматические расчёты

1. **quality_score** — рассчитывается при сохранении VideoReference:
   - +10 за `search_profile`
   - +5 за `public_summary`
   - +10 за наличие tutorials
   - +2 за каждый тег (максимум +10)

2. **completeness_flags** — рассчитывается при сохранении VideoReference:
   - `has_search_profile` (boolean)
   - `has_public_summary` (boolean)
   - `has_tutorials` (boolean)
   - `tags_count` (integer)

3. **search_vector** — автоматически генерируется PostgreSQL из `title`, `search_profile`, `search_metadata`

### Нормализация URL

- Автоматически выполняется при создании/обновлении VideoReference через `PlatformNormalizationService`
- Определяет платформу и извлекает `platform_video_id`
- Поддерживает различные форматы URL (включая короткие ссылки для TikTok)

### Фильтрация по платформам

- Поддерживает множественный выбор через массив
- Frontend использует чекбоксы вместо селектора
- Backend использует `whereIn()` для массива платформ

---

## 🔮 Будущие улучшения

### Планируемые функции

1. **Семантический поиск:**
   - Интеграция pgvector + embeddings для векторного поиска
   - Более точный поиск по смыслу

2. **Подборки референсов:**
   - Возможность создавать коллекции видео
   - Обмен подборками между пользователями

3. **Коммуникация:**
   - Раздел для обмена референсами между клиентами и видеографами
   - Комментарии и обсуждения

4. **Расширенные обучающие материалы:**
   - Более детальная структура tutorials
   - Интеграция с внешними образовательными платформами

---

## 📚 Дополнительная документация

- `video-player-architecture.md` — детальная архитектура видео-плееров
- `technical-implementation-plan.md` — технический план реализации
- `business-requirements.md` — бизнес-требования

---

**Последнее обновление:** 2026-01-12

