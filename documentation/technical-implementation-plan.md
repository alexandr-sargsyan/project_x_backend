# 🔧 Технический план реализации: Filmmaker Reference Platform

## 📋 Общая информация

**Технологический стек:**
- Backend: Laravel 12, PHP 8.4
- База данных: PostgreSQL (единственная БД, с full-text search через tsvector/tsquery)
- Админ-панель: React
- Фронтенд: React

**Примечание:** На этапе MVP используем только PostgreSQL с full-text search. В будущем (после MVP) можно добавить семантический/векторный поиск через pgvector + embeddings.

**Принцип разработки:** Backend → Админка → Фронтенд

---

## 🎯 Этап 1: Backend (Laravel)

### Шаг 1.1: Настройка проекта и окружения

**Что делаем:**
- Настраиваем окружение для работы с PostgreSQL
- Настраиваем full-text search в PostgreSQL

**Действия:**
1. Обновить `.env.example` файл (пользователь сам настроит `.env`):
   - Добавить настройки подключения к PostgreSQL
   - Убедиться, что PostgreSQL поддерживает full-text search

2. Установить пакеты через Composer:
   - Проверить наличие всех необходимых пакетов Laravel
   - Дополнительные пакеты не требуются (используем встроенные возможности PostgreSQL)

3. Настроить `config/database.php` для PostgreSQL

**Файлы для изменения:**
- `.env.example` (добавить примеры настроек PostgreSQL)
- `config/database.php`

---

### Шаг 1.2: Миграции базы данных

**Что делаем:**
- Создаём структуру таблиц в PostgreSQL согласно бизнес-требованиям
- Определяем связи между таблицами

**Миграции для создания:**

#### 1.2.1. Миграция `create_categories_table`
**Таблица:** `categories`
**Поля:**
- `id` (bigint, primary key)
- `name` (string, unique)
- `slug` (string, unique)
- `parent_id` (bigint, nullable, foreign key → categories.id) — для подкатегорий (adjacency list)
- `order` (integer, default 0) — для упорядочивания категорий
- `created_at`, `updated_at` (timestamps)

**Действия:**
```bash
php artisan make:migration create_categories_table
```

#### 1.2.2. Миграция `create_video_references_table`
**Таблица:** `video_references`
**Поля:**

**Display Fields:**
- `id` (bigint, primary key)
- `title` (string)
- `source_url` (string) — оригинальная ссылка
- `preview_embed` (text, nullable) — embed код
- `public_summary` (text, nullable) — короткое описание
- `details_public` (json, nullable) — дополнительные детали
- `duration_sec` (integer, nullable) — длительность в секундах

**Filter Fields:**
- `category_id` (bigint, foreign key → categories.id)
- `platform` (string, nullable) — определяется автоматически по URL (instagram, tiktok, youtube)
- `platform_video_id` (string, nullable) — ID видео на платформе после нормализации URL
- `pacing` (string, nullable) — темп видео (slow, fast, mixed)
- `hook_type` (string, nullable) — тип "хука"
- `production_level` (string, nullable) — уровень продакшена (low, mid, high)
- `has_visual_effects` (boolean, default false)
- `has_3d` (boolean, default false)
- `has_animations` (boolean, default false)
- `has_typography` (boolean, default false)
- `has_sound_design` (boolean, default false)

**Search Fields:**
- `search_profile` (text) — ключевая идея, структурированное описание
- `search_metadata` (text, nullable) — синонимы, ключевые слова

**Ранжирование:**
- `quality_score` (integer, default 0) — оценка качества
- `completeness_flags` (json, nullable) — флаги полноты данных

**Служебные:**
- `created_at`, `updated_at` (timestamps)

**Для full-text search:**
- Создать computed column или trigger для генерации `tsvector` из полей: `title`, `search_profile`, `search_metadata`
- Создать GIN индекс на `tsvector` колонку для быстрого поиска

**Дополнительная миграция:**
После создания основной таблицы нужно добавить поле `platform_video_id`:
- `platform_video_id` (string, nullable) — ID видео на платформе после нормализации URL

**Действия:**
```bash
php artisan make:migration create_video_references_table
php artisan make:migration add_platform_video_id_to_video_references_table
```

**После создания миграции добавить:**
- Computed column `search_vector` типа `tsvector` (или trigger для его генерации)
- GIN индекс на `search_vector` для full-text search

#### 1.2.3. Миграция `create_tags_table`
**Таблица:** `tags`
**Поля:**
- `id` (bigint, primary key)
- `name` (string, unique) — уникальное имя тега
- `created_at`, `updated_at` (timestamps)

**Действия:**
```bash
php artisan make:migration create_tags_table
```

#### 1.2.4. Миграция `create_video_reference_tag_table` (pivot)
**Таблица:** `video_reference_tag`
**Поля:**
- `id` (bigint, primary key)
- `video_reference_id` (bigint, foreign key)
- `tag_id` (bigint, foreign key)
- `created_at`, `updated_at` (timestamps)

**Действия:**
```bash
php artisan make:migration create_video_reference_tag_table
```

#### 1.2.5. Миграция `create_tutorials_table`
**Таблица:** `tutorials`
**Поля:**
- `id` (bigint, primary key)
- `video_reference_id` (bigint, foreign key → video_references.id)
- `tutorial_url` (string, nullable) — ссылка на внешний урок
- `label` (string, nullable) — название сегмента внутри видео
- `start_sec` (integer, nullable) — начало сегмента в секундах
- `end_sec` (integer, nullable) — конец сегмента в секундах
- `created_at`, `updated_at` (timestamps)

**Валидация на уровне приложения:**
- Хотя бы одно из полей должно быть заполнено: `tutorial_url` ИЛИ (`label` + `start_sec` + `end_sec`)

**Действия:**
```bash
php artisan make:migration create_tutorials_table
```

**После создания всех миграций:**
```bash
php artisan migrate
```

---

### Шаг 1.3: Модели Eloquent

**Что делаем:**
- Создаём модели с отношениями
- Настраиваем fillable, casts, отношения

**Модели для создания:**

#### 1.3.1. Модель `Category`
**Файл:** `app/Models/Category.php`

**Отношения:**
- `hasMany(VideoReference::class)`
- `belongsTo(Category::class, 'parent_id')` — родительская категория
- `hasMany(Category::class, 'parent_id')` — дочерние категории

**Поля:**
- `fillable`: name, slug, parent_id, order

**Методы:**
- `getChildren()` — получить дочерние категории
- `getParent()` — получить родительскую категорию
- `isRoot()` — проверка, является ли корневой категорией

**Действия:**
```bash
php artisan make:model Category
```

#### 1.3.2. Модель `Tag`
**Файл:** `app/Models/Tag.php`

**Отношения:**
- `belongsToMany(VideoReference::class)`

**Поля:**
- `fillable`: name

**Действия:**
```bash
php artisan make:model Tag
```

#### 1.3.3. Модель `VideoReference`
**Файл:** `app/Models/VideoReference.php`

**Отношения:**
- `belongsTo(Category::class)`
- `belongsToMany(Tag::class)`
- `hasMany(Tutorial::class)`

**Поля:**
- `fillable`: все поля кроме id, timestamps
- `casts`: 
  - has_* поля → boolean
  - duration_sec → integer (nullable)
  - quality_score → integer
  - completeness_flags → array
  - details_public → array (JSON)
  - created_at, updated_at → datetime

**Методы:**
- `getTagsTextAttribute()` — склеивает теги в строку для full-text search
- `getHasTutorialAttribute()` — проверяет наличие tutorials
- `calculateQualityScore()` — рассчитывает quality_score
- `calculateCompletenessFlags()` — рассчитывает completeness_flags

**Действия:**
```bash
php artisan make:model VideoReference
```

#### 1.3.4. Модель `Tutorial`
**Файл:** `app/Models/Tutorial.php`

**Отношения:**
- `belongsTo(VideoReference::class)`

**Поля:**
- `fillable`: video_reference_id, tutorial_url, label, start_sec, end_sec
- `casts`:
  - start_sec → integer (nullable)
  - end_sec → integer (nullable)

**Методы:**
- Валидация: проверка, что хотя бы одно из полей заполнено (tutorial_url ИЛИ label+start_sec+end_sec)

**Действия:**
```bash
php artisan make:model Tutorial
```

---

### Шаг 1.4: Request классы для валидации

**Что делаем:**
- Создаём Form Request классы для валидации данных

**Request классы:**

#### 1.4.1. `StoreVideoReferenceRequest`
**Файл:** `app/Http/Requests/StoreVideoReferenceRequest.php`

**Правила валидации:**
- `title` — required, string, max:255
- `source_url` — required, url
- `category_id` — required, exists:categories,id
- `platform` — nullable, string (определяется автоматически по URL)
- `pacing` — nullable, string
- `hook_type` — nullable, string
- `production_level` — nullable, string
- `duration_sec` — nullable, integer, min:1
- `tags` — required, array
- `tags.*` — exists:tags,id
- `search_profile` — required, string
- `search_metadata` — nullable, string
- `details_public` — nullable, array (JSON)
- Все checkbox поля — boolean
- Опциональные поля — nullable
- `tutorials` — nullable, array
- `tutorials.*.tutorial_url` — nullable, url
- `tutorials.*.label` — nullable, string
- `tutorials.*.start_sec` — nullable, integer, min:0
- `tutorials.*.end_sec` — nullable, integer, min:0
- Валидация tutorials: хотя бы одно из полей должно быть заполнено (tutorial_url ИЛИ label+start_sec+end_sec)

**Действия:**
```bash
php artisan make:request StoreVideoReferenceRequest
```

#### 1.4.2. `UpdateVideoReferenceRequest`
**Файл:** `app/Http/Requests/UpdateVideoReferenceRequest.php`

**Правила:** аналогично StoreVideoReferenceRequest, но все поля optional

**Действия:**
```bash
php artisan make:request UpdateVideoReferenceRequest
```

#### 1.4.3. `FilterVideoReferenceRequest`
**Файл:** `app/Http/Requests/FilterVideoReferenceRequest.php`

**Правила валидации:**
- `search` — nullable, string (поисковый запрос)
- `id` — nullable, integer (точный поиск по ID)
- `source_url` — nullable, string (точный поиск по URL)
- `category_id` — nullable, integer или array (поддержка множественного выбора)
- `category_id.*` — integer, exists:categories,id (если массив)
- `platform` — nullable, string
- `pacing` — nullable, string
- `hook_type` — nullable, string
- `production_level` — nullable, string
- `has_visual_effects` — nullable, boolean
- `has_3d` — nullable, boolean
- `has_animations` — nullable, boolean
- `has_typography` — nullable, boolean
- `has_sound_design` — nullable, boolean
- `has_tutorial` — nullable, boolean
- `tag_ids` — nullable, array (фильтрация по тегам, логика OR)
- `tag_ids.*` — integer, exists:tags,id
- `sort_by` — nullable, string
- `page` — nullable, integer, min:1
- `per_page` — nullable, integer, min:1, max:100

**Действия:**
```bash
php artisan make:request FilterVideoReferenceRequest
```

---

### Шаг 1.5: Сервисы для работы с поиском

**Что делаем:**
- Создаём сервис для full-text search в PostgreSQL
- Используем tsvector/tsquery для поиска

#### 1.5.1. Сервис `PostgresSearchService`
**Файл:** `app/Services/PostgresSearchService.php`

**Методы:**
- `search(string $query, array $filters = [])` — full-text search с фильтрами
- `buildSearchQuery(Builder $query, string $searchTerm, array $filters)` — построение запроса с поиском и фильтрами
- `buildFilters(Builder $query, array $filters)` — применение фильтров через WHERE

**Принцип работы:**
- Использует `to_tsvector()` для индексации текста из полей: `title`, `search_profile`, `search_metadata`
- Использует `to_tsquery()` для поисковых запросов
- Комбинирует full-text search с обычными WHERE условиями для фильтров
- Сортировка по `quality_score DESC, created_at DESC`

**Фильтрация по тегам:**
- Поддерживается фильтрация по массиву `tag_ids` (логика OR - хотя бы один из выбранных тегов)
- Используется `whereHas('tags')` с `whereIn('tags.id', $tagIds)`

**Пример SQL запроса:**
```sql
SELECT * FROM video_references
WHERE to_tsvector('russian', 
    coalesce(title, '') || ' ' || 
    coalesce(search_profile, '') || ' ' || 
    coalesce(search_metadata, '')
) @@ to_tsquery('russian', :query)
AND category_id = :category_id
AND platform = :platform
AND has_visual_effects = :has_visual_effects
AND EXISTS (
    SELECT 1 FROM video_reference_tag 
    WHERE video_reference_id = video_references.id 
    AND tag_id IN (:tag_ids)
)
ORDER BY quality_score DESC, created_at DESC
```

**Действия:**
Создать файл вручную: `app/Services/PostgresSearchService.php`

---

### Шаг 1.6: Контроллеры

**Что делаем:**
- Создаём контроллеры для API endpoints

#### 1.6.1. Контроллер `VideoReferenceController`
**Файл:** `app/Http/Controllers/VideoReferenceController.php`

**Методы:**

**`index(FilterVideoReferenceRequest $request)`**
- Получает список видео с фильтрацией и поиском
- Использует `PostgresSearchService` для full-text search в PostgreSQL
- Комбинирует поиск (tsvector/tsquery) с фильтрами (WHERE условия)
- Возвращает пагинированный результат
- Включает отношения: category, tags, tutorials

**`show(int $id)`**
- Получает одно видео по ID
- Включает все отношения: category, tags, tutorials
- Возвращает 404 если не найдено

**`store(StoreVideoReferenceRequest $request)`**
- Создаёт новое видео
- Автоматически определяет `platform` по `source_url` используя `PlatformEnum::fromUrl()` (если не указан)
- Сохраняет в PostgreSQL
- Обновляет `search_vector` (tsvector) через trigger или computed column
- Рассчитывает quality_score и completeness_flags
- Возвращает созданное видео

**Примечание:** `platform` определяется автоматически по URL используя `PlatformEnum::fromUrl()`:
- Если URL содержит `instagram.com` → `PlatformEnum::INSTAGRAM`
- Если URL содержит `tiktok.com` → `PlatformEnum::TIKTOK`
- Если URL содержит `youtube.com` или `youtu.be` → `PlatformEnum::YOUTUBE`

**`update(UpdateVideoReferenceRequest $request, int $id)`**
- Обновляет существующее видео
- Автоматически определяет `platform` по `source_url` используя `PlatformEnum::fromUrl()` (если source_url изменился)
- Обновляет в PostgreSQL
- Обновляет `search_vector` (tsvector) через trigger или computed column
- Пересчитывает quality_score и completeness_flags
- Возвращает обновлённое видео

**`destroy(int $id)`**
- Удаляет видео из PostgreSQL
- Возвращает успешный ответ

**Действия:**
```bash
php artisan make:controller VideoReferenceController --api
```

#### 1.6.2. Контроллер `CategoryController`
**Файл:** `app/Http/Controllers/CategoryController.php`

**Методы:**
- `index()` — список всех категорий
- `show(int $id)` — одна категория

**Действия:**
```bash
php artisan make:controller CategoryController --api
```

#### 1.6.3. Контроллер `TagController`
**Файл:** `app/Http/Controllers/TagController.php`

**Методы:**
- `index()` — список всех тегов
- `show(int $id)` — один тег

**Действия:**
```bash
php artisan make:controller TagController --api
```

---

### Шаг 1.7: API Routes

**Что делаем:**
- Настраиваем API маршруты

**Файл:** `routes/api.php`

**Маршруты:**
```php
// Video References
Route::get('/video-references', [VideoReferenceController::class, 'index']);
Route::get('/video-references/{id}', [VideoReferenceController::class, 'show']);
Route::post('/video-references', [VideoReferenceController::class, 'store']);
Route::put('/video-references/{id}', [VideoReferenceController::class, 'update']);
Route::delete('/video-references/{id}', [VideoReferenceController::class, 'destroy']);

// Categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

// Tags
Route::get('/tags', [TagController::class, 'index']);
Route::get('/tags/{id}', [TagController::class, 'show']);
```

**Действия:**
Отредактировать `routes/api.php`

---

### Шаг 1.8: Seeders для начальных данных

**Что делаем:**
- Создаём seeders для категорий и тегов
- Добавляем тестовые видео-референсы (20-30 штук)

#### 1.8.1. Seeder `CategorySeeder`
**Файл:** `database/seeders/CategorySeeder.php`

**Данные:**
- Реклама
- Документалистика
- Музыкальные клипы
- Корпоративные видео
- Социальные сети
- И т.д.

**Действия:**
```bash
php artisan make:seeder CategorySeeder
```

#### 1.8.2. Seeder `TagSeeder`
**Файл:** `database/seeders/TagSeeder.php`

**Данные:**
- Различные теги для видео (переходы, стили, техники)

**Действия:**
```bash
php artisan make:seeder TagSeeder
```

#### 1.8.3. Seeder `VideoReferenceSeeder`
**Файл:** `database/seeders/VideoReferenceSeeder.php`

**Данные:**
- 20-30 тестовых видео-референсов с заполненными полями
- После создания каждого видео — `search_vector` (tsvector) обновляется автоматически через trigger

**Действия:**
```bash
php artisan make:seeder VideoReferenceSeeder
```

#### 1.8.4. Обновить `DatabaseSeeder`
**Файл:** `database/seeders/DatabaseSeeder.php`

**Вызовы:**
```php
$this->call([
    CategorySeeder::class,
    TagSeeder::class,
    VideoReferenceSeeder::class,
]);
```

**Запуск:**
```bash
php artisan db:seed
```

---

---

### Шаг 1.10: Тестирование Backend

**Что делаем:**
- Создаём базовые тесты для API endpoints

**Тесты:**
- `tests/Feature/VideoReferenceTest.php` — тесты CRUD операций
- `tests/Feature/VideoReferenceSearchTest.php` — тесты поиска
- `tests/Feature/VideoReferenceFilterTest.php` — тесты фильтрации

**Действия:**
```bash
php artisan make:test VideoReferenceTest
php artisan make:test VideoReferenceSearchTest
php artisan make:test VideoReferenceFilterTest
```

**Запуск:**
```bash
php artisan test
```

---

## 🎨 Этап 2: Админ-панель (React)

**Принцип:** Максимально простая админ-панель без авторизации, без видео-плееров, без превью. Только базовый CRUD функционал.

### Шаг 2.1: Настройка проекта React

**Что делаем:**
- Создаём новый React проект для админ-панели
- Настраиваем структуру проекта

**Действия:**
```bash
npx create-react-app project_x-admin-panel
cd project_x-admin-panel
```

**Установка зависимостей:**
```bash
npm install axios react-router-dom
```

**Структура папок:**
```
project_x-admin-panel/
├── src/
│   ├── components/
│   │   ├── Sidebar.jsx          # Боковое меню (2 пункта)
│   │   ├── VideoReference/
│   │   │   ├── VideoReferenceForm.jsx
│   │   │   └── VideoReferenceList.jsx
│   │   └── Category/
│   │       ├── CategoryForm.jsx
│   │       └── CategoryList.jsx
│   ├── services/
│   │   └── api.js
│   ├── pages/
│   │   ├── VideoReferences.jsx
│   │   └── Categories.jsx
│   └── App.jsx
```

---

### Шаг 2.2: Настройка API клиента

**Что делаем:**
- Создаём сервис для работы с API

**Файл:** `src/services/api.js`

**Методы для Video References:**
- `getVideoReferences()` — список всех видео
- `getVideoReference(id)` — одно видео по ID
- `searchVideoReferences(id, sourceUrl)` — поиск по ID и source_url
- `createVideoReference(data)` — создание
- `updateVideoReference(id, data)` — обновление
- `deleteVideoReference(id)` — удаление

**Методы для Categories:**
- `getCategories()` — список всех категорий
- `getCategory(id)` — одна категория по ID
- `createCategory(data)` — создание
- `updateCategory(id, data)` — обновление
- `deleteCategory(id)` — удаление

**Методы для Tags:**
- `getTags()` — список всех тегов
- `createTag(name)` — создание тега (если не существует)

**Логика тегов:**
- При сохранении видео-референса: теги передаются как строка через запятую
- Для каждого тега: проверить существование по имени, если нет — создать новый
- Привязать все теги к видео-референсу

**Действия:**
Создать файл `src/services/api.js`

---

### Шаг 2.3: Компонент бокового меню

**Что делаем:**
- Создаём простое боковое меню с 2 пунктами

**Файл:** `src/components/Sidebar.jsx`

**Структура:**
- Фиксированное меню слева
- 2 пункта:
  1. Categories — ссылка на `/categories`
  2. Video References — ссылка на `/video-references`

**Действия:**
Создать файл `src/components/Sidebar.jsx`

---

### Шаг 2.4: Компонент формы добавления/редактирования видео

**Что делаем:**
- Создаём форму с полями для всех трёх слоёв (Display, Filter, Search)

**Файл:** `src/components/VideoReference/VideoReferenceForm.jsx`

**Секции формы:**

**1. Display Fields:**
- Title (input, required)
- Source URL (input, required) — оригинальная ссылка на видео
- Preview Embed (textarea, optional)
- Public Summary (textarea, optional)
- Details Public (textarea, optional) — JSON в виде текста
- Duration (seconds) — не отображается в форме (для будущей автоматизации)

**2. Filter Fields:**
- Category Selection (отдельный блок):
  - Два селектора размещены side-by-side:
    - Parent Category (select, required, слева) — показывает все корневые категории
    - Subcategory (select, справа) — изначально пуст и disabled
  - Логика:
    - Если у выбранной родительской категории есть подкатегории:
      - Subcategory становится активным и обязательным
      - Отображаются только подкатегории выбранной родительской категории
      - Можно выбрать только подкатегорию (не родительскую)
    - Если у выбранной родительской категории нет подкатегорий:
      - Subcategory остается disabled
      - Можно выбрать саму родительскую категорию
- Platform — определяется автоматически на бэкенде, не отображается в форме
- Pacing (select: slow/fast/mixed, optional)
- Hook Type (input, optional) — текстовое поле для указания типа "хука" (зацепки) в видео (например, "question", "action", "visual", "emotional", "mystery", "sound")
- Production Level (select: low/mid/high, optional)
- Checkboxes:
  - Has Visual Effects
  - Has 3D
  - Has Animations
  - Has Typography
  - Has Sound Design

**3. Search Fields:**
- Search Profile (textarea, required) — ключевая идея видео, структурированное описание
- Search Metadata (textarea, optional) — синонимы, ключевые слова, альтернативные термины для улучшения поиска

**4. Tags:**
- Tags (TagsInput component, required) — массив тегов с автодополнением
  - Инпут с кнопкой "Add" для добавления тегов
  - Бейджи выбранных тегов с возможностью удаления (×)
  - Автодополнение при вводе (live search с debounce 300ms)
  - Модальное окно для просмотра всех тегов
  - Валидация: только латинские буквы, без пробелов
  - Минимум 1 тег при создании
  - Отправляется как массив строк (вместо строки с запятыми)

**5. Tutorials (опционально):**
- Список tutorials с возможностью добавления/удаления
- Для каждого tutorial:
  - Tutorial URL (optional) — ссылка на внешний урок
  - Label (optional) — название сегмента
  - Start Sec (optional) — начало в секундах
  - End Sec (optional) — конец в секундах
  - Валидация: хотя бы одно из полей должно быть заполнено (URL ИЛИ label+start+end)

**Валидация:**
- Валидация обязательных полей (title, source_url, category_id, search_profile)
- Валидация URL
- Валидация числовых полей

**Действия:**
Создать файл `src/components/VideoReference/VideoReferenceForm.jsx`

---

### Шаг 2.5: Компонент списка видео

**Что делаем:**
- Создаём компонент для отображения списка видео-референсов

**Файл:** `src/components/VideoReference/VideoReferenceList.jsx`

**Функционал:**
- Простой список (таблица) с видео
- Колонки: ID, Title, Source URL, Category, Actions
- Кнопки: Edit, Delete
- Поиск:
  - По ID (input, точное совпадение)
  - По Source URL (input, частичное совпадение)
- Без превью, без видео-плееров, без сортировки
- Без пагинации (показывать все результаты)

**Логика удаления:**
- Кнопка Delete рядом с каждым видео
- При клике: подтверждение (confirm dialog)
- После подтверждения: отправка DELETE запроса на `/api/video-references/{id}`
- После успешного удаления: обновить список

**Действия:**
Создать файл `src/components/VideoReference/VideoReferenceList.jsx`

---

### Шаг 2.6: Компонент формы категорий

**Что делаем:**
- Создаём форму для добавления/редактирования категорий

**Файл:** `src/components/Category/CategoryForm.jsx`

**Поля:**
- Name (input, required)
- Slug (input, required)
- Parent Category (select, optional) — выпадающий список всех категорий (для подкатегорий)
- Order (input, optional) — число для упорядочивания

**Действия:**
Создать файл `src/components/Category/CategoryForm.jsx`

---

### Шаг 2.7: Компонент списка категорий

**Что делаем:**
- Создаём компонент для отображения списка категорий

**Файл:** `src/components/Category/CategoryList.jsx`

**Функционал:**
- Простой список (таблица) с категориями
- Колонки: ID, Name, Slug, Parent, Order, Actions
- Кнопки: Edit, Delete
- Отображение иерархии (если есть подкатегории)

**Логика удаления:**
- Кнопка Delete рядом с каждой категорией
- При клике: подтверждение (confirm dialog)
- Проверка: нельзя удалить категорию, если:
  - Есть дочерние категории
  - Есть видео-референсы в этой категории
- После подтверждения: отправка DELETE запроса на `/api/categories/{id}`
- После успешного удаления: обновить список

**Действия:**
Создать файл `src/components/Category/CategoryList.jsx`

---

### Шаг 2.8: Страницы админ-панели

**Что делаем:**
- Создаём страницы для управления контентом

#### 2.8.1. Страница Video References
**Файл:** `src/pages/VideoReferences.jsx`

**Функционал:**
- Отображает компонент `VideoReferenceList`
- Кнопка "Add Video Reference" — открывает форму создания
- Модальное окно или отдельная страница для формы создания/редактирования
- Поиск по ID и Source URL
- Редактирование: клик по Edit → открыть форму редактирования
- Удаление: клик по Delete → подтверждение → удаление

#### 2.8.2. Страница Categories
**Файл:** `src/pages/Categories.jsx`

**Функционал:**
- Отображает компонент `CategoryList`
- Кнопка "Add Category" — открывает форму создания
- Модальное окно или отдельная страница для формы создания/редактирования
- Редактирование: клик по Edit → открыть форму редактирования
- Удаление: клик по Delete → проверка на дочерние категории и видео → подтверждение → удаление

**Действия:**
Создать файлы страниц

---

### Шаг 2.6: Настройка роутинга

**Что делаем:**
- Настраиваем React Router для навигации

**Файл:** `src/App.jsx`

**Маршруты:**
- `/` — Dashboard
- `/video-references` — список видео
- `/video-references/create` — создание
- `/video-references/:id/edit` — редактирование
- `/categories` — категории

**Действия:**
Настроить React Router в `src/App.jsx`

---

---

### Шаг 2.8: Обработка ошибок и уведомления

**Что делаем:**
- Добавляем обработку ошибок API
- Система уведомлений (toast)

**Установка:**
```bash
npm install react-toastify
```

**Использование:**
- Показывать успешные сообщения при создании/обновлении
- Показывать ошибки при неудачных запросах

---

## 🌐 Этап 3: Фронтенд для пользователей (React)

**Важно:** Фронтенд должен быть полностью респонсивным и работать на всех устройствах:
- 📱 Мобильные телефоны (вертикальная ориентация)
- 📱 Планшеты (вертикальная и горизонтальная ориентация)
- 💻 Ноутбуки
- 🖥️ Десктопы

**Принцип дизайна:** Максимально простой и удобный интерфейс, вдохновленный TikTok для списка видео и детального просмотра, Wildberries для категорий.

### Шаг 3.1: Настройка проекта React

**Что делаем:**
- Создаём новый React проект для фронтенда

**Действия:**
```bash
npx create-react-app project_x_frontend
cd project_x_frontend
```

**Установка зависимостей:**
```bash
npm install axios react-router-dom @tanstack/react-query
npm install -D @types/react @types/react-dom
```

**Примечание:** Для респонсивного дизайна используем CSS Grid, Flexbox и media queries. Рекомендуется использовать CSS-фреймворк (например, Tailwind CSS) или собственные адаптивные стили.

**Структура папок:**
```
project_x_frontend/
├── src/
│   ├── components/
│   │   ├── CategorySidebar/
│   │   │   └── CategorySidebar.jsx
│   │   ├── VideoGrid/
│   │   │   └── VideoGrid.jsx
│   │   ├── VideoCard/
│   │   │   └── VideoCard.jsx
│   │   ├── SearchBar/
│   │   │   └── SearchBar.jsx
│   │   ├── Filters/
│   │   │   └── Filters.jsx
│   │   ├── VideoDetailView/
│   │   │   ├── VideoDetailView.jsx
│   │   │   └── VideoDetailSidebar.jsx
│   │   ├── TutorialCard/
│   │   │   └── TutorialCard.jsx
│   │   └── TagBadge/
│   │       └── TagBadge.jsx
│   ├── services/
│   │   └── api.js
│   ├── pages/
│   │   ├── Home.jsx
│   │   ├── VideoDetail.jsx
│   │   └── Search.jsx
│   ├── styles/
│   │   └── responsive.css
│   └── App.jsx
```

---

### Шаг 3.2: Настройка API клиента

**Что делаем:**
- Создаём сервис для работы с API

**Файл:** `src/services/api.js`

**Методы:**
- `searchVideoReferences(query, filters)` — поиск с фильтрами
- `getVideoReference(id)` — одно видео
- `getCategories()` — список категорий
- `getTags()` — список тегов

**Действия:**
Создать файл `src/services/api.js`

---

### Шаг 3.3: Компонент боковой панели категорий

**Что делаем:**
- Создаём компонент для отображения категорий и подкатегорий (как в Wildberries)

**Файл:** `src/components/CategorySidebar/CategorySidebar.jsx`

**Функционал:**
- Вертикальный список категорий слева
- Иерархическое отображение категорий и подкатегорий
- Возможность раскрытия/сворачивания подкатегорий
- При клике на категорию — фильтрация видео по этой категории
- Активная категория выделяется визуально
- Респонсивность: на мобильных устройствах может быть скрыта или доступна через меню

**Отображает:**
- Название категории
- Иконка/стрелка для подкатегорий
- Количество видео в категории (опционально)

**Действия:**
Создать файл `src/components/CategorySidebar/CategorySidebar.jsx`

### Шаг 3.4: Компонент сетки видео (TikTok-стиль)

**Что делаем:**
- Создаём компонент для отображения списка видео в стиле TikTok

**Файл:** `src/components/VideoGrid/VideoGrid.jsx`

**Функционал:**
- Вертикальная сетка видео (как в TikTok)
- Каждое видео занимает всю ширину контейнера
- Превью видео с автоплеем при прокрутке (lazy loading)
- При клике на видео — переход на детальную страницу
- Бесконечная прокрутка (infinite scroll) или пагинация
- Респонсивность: адаптация под размер экрана

**Действия:**
Создать файл `src/components/VideoGrid/VideoGrid.jsx`

### Шаг 3.5: Компонент карточки видео

**Что делаем:**
- Создаём компонент для отображения одного видео в сетке

**Файл:** `src/components/VideoCard/VideoCard.jsx`

**Отображает:**
- Превью видео (с автоплеем на hover/при прокрутке, если возможно)
- Title (наложен на превью или под ним)
- Platform (иконка: Instagram, TikTok, YouTube)
- Category (бейдж)
- Duration (если доступно)
- При клике — переход на детальную страницу

**Респонсивность:**
- На мобильных: полноширинная карточка
- На планшетах: 2 колонки
- На десктопах: 1-2 колонки в зависимости от размера экрана

**Действия:**
Создать файл `src/components/VideoCard/VideoCard.jsx`

---

### Шаг 3.4: Компонент поиска

**Что делаем:**
- Создаём компонент поисковой строки

**Файл:** `src/components/SearchBar/SearchBar.jsx`

**Функционал:**
- Поле ввода для поискового запроса
- Debounce для оптимизации запросов
- Отправка запроса в API
- Отображение результатов поиска

**Действия:**
Создать файл `src/components/SearchBar/SearchBar.jsx`

---

### Шаг 3.5: Компонент фильтров

**Что делаем:**
- Создаём компонент для фильтрации видео

**Файл:** `src/components/Filters/Filters.jsx`

**Фильтры:**
- Category (select)
- Platform (select)
- Pacing (select)
- Hook Type (input)
- Production Level (select)
- Checkboxes для has_* полей
- Has Tutorial (checkbox)

**Функционал:**
- Применение фильтров
- Сброс фильтров
- Комбинирование фильтров
- Отправка запроса с фильтрами в API

**Действия:**
Создать файл `src/components/Filters/Filters.jsx`

---

---

### Шаг 3.12: Страницы фронтенда

**Что делаем:**
- Создаём основные страницы приложения

#### 3.12.1. Главная страница (каталог)
**Файл:** `src/pages/Home.jsx`

**Структура страницы:**
- **Верхняя часть:**
  - Поисковая строка (`SearchBar`)
  - Фильтры (`Filters`)

- **Основная часть (горизонтальная раскладка):**
  - **Левая колонка (фиксированная ширина, например 250px на десктопе):**
    - Боковая панель категорий (`CategorySidebar`)
    - На мобильных: может быть скрыта или доступна через меню-гамбургер

  - **Правая колонка (остальное пространство):**
    - Сетка видео (`VideoGrid`)
    - Вертикальная прокрутка
    - Бесконечная прокрутка или пагинация

**Функционал:**
- Поиск по тексту (full-text search)
- Фильтрация по категориям (через боковую панель)
- Фильтрация по другим параметрам (через фильтры)
- Отображение видео в стиле TikTok
- Респонсивность: на мобильных боковая панель скрыта или доступна через меню

**Респонсивность:**
- **Мобильные (< 768px):**
  - Боковая панель категорий скрыта или доступна через меню-гамбургер
  - Поиск и фильтры вверху (возможно, свернуты в аккордеон)
  - Видео на всю ширину, вертикальная прокрутка

- **Планшеты (768px - 1024px):**
  - Боковая панель может быть скрыта или узкая
  - Видео на всю ширину или 2 колонки
  - Поиск и фильтры вверху

- **Десктопы (> 1024px):**
  - Боковая панель слева (фиксированная ширина)
  - Видео справа (1-2 колонки)
  - Поиск и фильтры вверху

#### 3.12.2. Страница детального просмотра видео (TikTok-стиль)
**Файл:** `src/pages/VideoDetail.jsx`

**Структура страницы:**
- **Основной контейнер (полноэкранный):**
  - Видео-плеер/превью (занимает большую часть экрана)
  - Правая боковая панель (`VideoDetailSidebar`)

**Функционал:**
- Загрузка данных видео по ID
- Отображение видео в полноэкранном режиме (как в TikTok)
- Вертикальная прокрутка между видео (опционально, для навигации к следующему/предыдущему)
- Правая панель с деталями:
  - Кнопка редиректа на оригинальный сайт
  - Список тегов
  - Детали (tutorials и checkboxes)
- Кнопка "назад к каталогу" (вверху или через жесты на мобильных)

**Респонсивность:**
- **Мобильные (< 768px):**
  - Видео на весь экран
  - Правая панель доступна через кнопку/свайп снизу или как модальное окно
  - Вертикальная прокрутка

- **Планшеты (768px - 1024px):**
  - Видео слева, панель справа (если позволяет место)
  - Или видео на весь экран, панель через кнопку

- **Десктопы (> 1024px):**
  - Видео слева (60-70% ширины)
  - Панель справа (30-40% ширины)
  - Фиксированная раскладка

**Действия:**
Создать файлы страниц

---

### Шаг 3.13: Настройка роутинга

**Что делаем:**
- Настраиваем React Router

**Файл:** `src/App.jsx`

**Маршруты:**
- `/` — главная (каталог с категориями и видео)
- `/video/:id` — детальная страница видео (TikTok-стиль)

**Действия:**
Настроить React Router в `src/App.jsx`

### Шаг 3.14: Респонсивный дизайн и стили

**Что делаем:**
- Создаём адаптивные стили для всех компонентов

**Файл:** `src/styles/responsive.css`

**Breakpoints:**
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

**Принципы:**
- Mobile-first подход
- Использование CSS Grid и Flexbox
- Media queries для адаптации
- Touch-friendly интерфейс для мобильных
- Плавные переходы и анимации

**Действия:**
Создать файл `src/styles/responsive.css` и интегрировать в компоненты

---

### Шаг 3.15: Интеграция поиска с API

**Что делаем:**
- Настраиваем отправку поисковых запросов через API
- Обработка результатов поиска

**В компоненте SearchBar:**
- Отправка запроса в `/api/video-references` с параметром `search`
- Backend выполняет full-text search в PostgreSQL через tsvector/tsquery
- Отображение результатов

**В компоненте Filters:**
- Отправка фильтров в `/api/video-references` с параметрами фильтрации
- Комбинирование поиска (full-text) и фильтров (WHERE условия) на backend
- Backend использует `PostgresSearchService` для объединения поиска и фильтров

**Действия:**
Интегрировать поиск в компоненты

---

### Шаг 3.16: Оптимизация и UX

**Что делаем:**
- Добавляем loading состояния
- Обработка ошибок
- Пагинация
- Бесконечная прокрутка (опционально)
- Кэширование данных

**Установка:**
```bash
npm install react-query
```

**Использование:**
- Кэширование результатов поиска
- Оптимистичные обновления
- Автоматическая повторная загрузка

---

## ✅ Чек-лист завершения MVP

### Backend:
- [ ] Все миграции созданы и применены
- [ ] Все модели созданы с отношениями
- [ ] Все контроллеры реализованы
- [ ] API endpoints работают
- [ ] Full-text search в PostgreSQL работает (tsvector/tsquery)
- [ ] GIN индексы созданы для быстрого поиска
- [ ] Seeders созданы и заполнены данными
- [ ] Базовые тесты написаны и проходят

### Админ-панель:
- [ ] Боковое меню с 2 пунктами работает
- [ ] Страница Categories работает (список, добавление, редактирование, удаление)
- [ ] Страница Video References работает (список, добавление, редактирование, удаление)
- [ ] Поиск по ID и Source URL работает
- [ ] Логика тегов работает (создание новых при необходимости)
- [ ] Удаление категорий с проверкой на дочерние элементы работает
- [ ] Удаление видео-референсов работает
- [ ] Валидация форм работает

### Фронтенд:
- [ ] Каталог видео отображается (TikTok-стиль)
- [ ] Боковая панель категорий работает (Wildberries-стиль)
- [ ] Поиск работает (full-text search)
- [ ] Фильтры работают
- [ ] Детальная страница видео работает (TikTok-стиль)
- [ ] Правая боковая панель деталей работает:
  - [ ] Кнопка редиректа на оригинальный сайт
  - [ ] Список тегов отображается
  - [ ] Tutorials отображаются с временными метками (формат "1:24 - 1:39")
  - [ ] Checkboxes для эффектов отображаются
- [ ] Респонсивность работает на всех устройствах:
  - [ ] Мобильные телефоны
  - [ ] Планшеты
  - [ ] Ноутбуки
  - [ ] Десктопы
- [ ] Пагинация или бесконечная прокрутка работает
- [ ] Lazy loading для видео-превью работает

---

## 🚀 Следующие шаги после MVP

1. Добавить аутентификацию и авторизацию
2. Добавить систему подписок
3. Добавить семантический/векторный поиск через pgvector + embeddings (опционально)
4. Добавить аналитику
5. Оптимизировать производительность
6. Добавить тесты для фронтенда и админки

