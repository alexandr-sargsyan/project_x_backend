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
- `preview_url` (string, nullable) — URL превью
- `preview_embed` (text, nullable) — embed код
- `public_summary` (text, nullable) — короткое описание
- `details_public` (json, nullable) — дополнительные детали
- `duration_sec` (integer, nullable) — длительность в секундах

**Filter Fields:**
- `category_id` (bigint, foreign key → categories.id)
- `platform` (string, nullable) — определяется автоматически по URL (instagram, tiktok, youtube)
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

**Действия:**
```bash
php artisan make:migration create_video_references_table
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
- `category_id` — nullable, exists:categories,id
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

### Шаг 2.1: Настройка проекта React

**Что делаем:**
- Создаём новый React проект для админ-панели
- Настраиваем структуру проекта

**Действия:**
```bash
npx create-react-app admin-panel
cd admin-panel
```

**Установка зависимостей:**
```bash
npm install axios react-router-dom @tanstack/react-query
npm install -D @types/react @types/react-dom
```

**Структура папок:**
```
admin-panel/
├── src/
│   ├── components/
│   │   ├── VideoReference/
│   │   │   ├── VideoReferenceForm.jsx
│   │   │   ├── VideoReferenceList.jsx
│   │   │   └── VideoReferenceCard.jsx
│   │   └── common/
│   │       ├── Input.jsx
│   │       ├── Select.jsx
│   │       └── Checkbox.jsx
│   ├── services/
│   │   └── api.js
│   ├── hooks/
│   │   └── useVideoReferences.js
│   ├── pages/
│   │   ├── Dashboard.jsx
│   │   ├── VideoReferences/
│   │   │   ├── List.jsx
│   │   │   └── Create.jsx
│   │   └── Categories.jsx
│   └── App.jsx
```

---

### Шаг 2.2: Настройка API клиента

**Что делаем:**
- Создаём сервис для работы с API

**Файл:** `src/services/api.js`

**Методы:**
- `getVideoReferences(filters)` — список видео
- `getVideoReference(id)` — одно видео
- `createVideoReference(data)` — создание
- `updateVideoReference(id, data)` — обновление
- `deleteVideoReference(id)` — удаление
- `getCategories()` — список категорий
- `getTags()` — список тегов
- `uploadVideo(file)` — загрузка видео
- `uploadPreview(file)` — загрузка превью

**Действия:**
Создать файл `src/services/api.js`

---

### Шаг 2.3: Компонент формы добавления/редактирования видео

**Что делаем:**
- Создаём форму с полями для всех трёх слоёв (Display, Filter, Search)

**Файл:** `src/components/VideoReference/VideoReferenceForm.jsx`

**Секции формы:**

**1. Display Fields:**
- Title (input)
- Source URL (input) — оригинальная ссылка на видео (используется для кнопки "Open original")
- Preview URL (input) — URL превью (ссылка, не файл)
- Preview Embed (textarea) — embed код
- Public Summary (textarea)
- Details Public (JSON editor, optional) — дополнительные детали в формате JSON
- Duration (seconds) (input, optional)
- Tags (multi-select)

**2. Filter Fields:**
- Category (select)
- Platform (read-only, определяется автоматически по Source URL)
- Pacing (select: slow/fast/mixed, optional)
- Hook Type (input, optional)
- Production Level (select: low/mid/high, optional)
- Checkboxes:
  - Has Visual Effects
  - Has 3D
  - Has Animations
  - Has Typography
  - Has Sound Design

**3. Search Fields:**
- Search Profile (textarea) — ключевая идея
- Search Metadata (textarea, optional) — синонимы

**4. Tutorials (опционально):**
- Список tutorials с возможностью добавления/удаления
- Для каждого tutorial:
  - Tutorial URL (optional) — ссылка на внешний урок
  - Label (optional) — название сегмента
  - Start Sec (optional) — начало в секундах
  - End Sec (optional) — конец в секундах
  - Валидация: хотя бы одно из полей должно быть заполнено (URL ИЛИ label+start+end)

**Валидация:**
- Валидация обязательных полей
- Валидация URL
- Валидация числовых полей

**Действия:**
Создать файл `src/components/VideoReference/VideoReferenceForm.jsx`

---

### Шаг 2.4: Компонент списка видео

**Что делаем:**
- Создаём компонент для отображения списка видео-референсов

**Файл:** `src/components/VideoReference/VideoReferenceList.jsx`

**Функционал:**
- Таблица или карточки с видео
- Колонки: ID, Title, Category, Platform, Duration, Actions
- Кнопки: Edit, Delete
- Пагинация
- Фильтрация (опционально)

**Действия:**
Создать файл `src/components/VideoReference/VideoReferenceList.jsx`

---

### Шаг 2.5: Страницы админ-панели

**Что делаем:**
- Создаём страницы для управления контентом

#### 2.5.1. Страница списка видео
**Файл:** `src/pages/VideoReferences/List.jsx`

**Функционал:**
- Отображает список всех видео
- Кнопка "Добавить новое видео"
- Фильтры и поиск
- Пагинация

#### 2.5.2. Страница создания видео
**Файл:** `src/pages/VideoReferences/Create.jsx`

**Функционал:**
- Форма создания нового видео
- Валидация URL полей
- Сохранение через API

#### 2.5.3. Страница редактирования видео
**Файл:** `src/pages/VideoReferences/Edit.jsx`

**Функционал:**
- Загрузка данных видео по ID
- Форма редактирования
- Сохранение изменений

#### 2.5.4. Страница управления категориями
**Файл:** `src/pages/Categories.jsx`

**Функционал:**
- Список категорий
- Добавление/редактирование категорий

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

### Шаг 3.1: Настройка проекта React

**Что делаем:**
- Создаём новый React проект для фронтенда

**Действия:**
```bash
npx create-react-app frontend
cd frontend
```

**Установка зависимостей:**
```bash
npm install axios react-router-dom @tanstack/react-query
npm install -D @types/react @types/react-dom
```

**Структура папок:**
```
frontend/
├── src/
│   ├── components/
│   │   ├── VideoCard/
│   │   │   └── VideoCard.jsx
│   │   ├── SearchBar/
│   │   │   └── SearchBar.jsx
│   │   ├── Filters/
│   │   │   └── Filters.jsx
│   │   └── VideoDetail/
│   │       └── VideoDetail.jsx
│   ├── services/
│   │   └── api.js
│   ├── pages/
│   │   ├── Home.jsx
│   │   ├── VideoDetail.jsx
│   │   └── Search.jsx
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

### Шаг 3.3: Компонент карточки видео

**Что делаем:**
- Создаём компонент для отображения видео в каталоге

**Файл:** `src/components/VideoCard/VideoCard.jsx`

**Отображает:**
- Превью (с автоплеем на hover, если возможно)
- Title
- Platform (с иконкой)
- Category (бейдж)
- Tags
- Duration
- Ссылка на детальную страницу

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

### Шаг 3.6: Компонент детальной страницы видео

**Что делаем:**
- Создаём компонент для отображения полной информации о видео

**Файл:** `src/components/VideoDetail/VideoDetail.jsx`

**Отображает:**
- Превью/плеер
- Title
- Platform с иконкой
- Category
- Tags
- Duration
- Public Summary
- Details Public (если есть, JSON)
- Checkbox flags (visual effects, 3D, animations, typography, sound design)
- Source URL (кнопка "Open original")
- Tutorials (если есть):
  - Список tutorials с ссылками на внешние уроки (если есть tutorial_url)
  - Сегменты с временными метками (если есть label+start_sec+end_sec) — кнопки вида 1:24–1:39
  - Могут быть оба одновременно (и URL, и сегмент)

**Действия:**
Создать файл `src/components/VideoDetail/VideoDetail.jsx`

---

### Шаг 3.7: Страницы фронтенда

**Что делаем:**
- Создаём основные страницы приложения

#### 3.7.1. Главная страница (каталог)
**Файл:** `src/pages/Home.jsx`

**Функционал:**
- Поисковая строка
- Фильтры
- Сетка карточек видео
- Пагинация
- Сортировка

#### 3.7.2. Страница детальной информации
**Файл:** `src/pages/VideoDetail.jsx`

**Функционал:**
- Загрузка данных видео по ID
- Отображение полной информации
- Ссылка "назад к каталогу"

#### 3.7.3. Страница поиска
**Файл:** `src/pages/Search.jsx`

**Функционал:**
- Поисковая строка
- Результаты поиска
- Фильтры для уточнения поиска

**Действия:**
Создать файлы страниц

---

### Шаг 3.8: Настройка роутинга

**Что делаем:**
- Настраиваем React Router

**Файл:** `src/App.jsx`

**Маршруты:**
- `/` — главная (каталог)
- `/search` — страница поиска
- `/video/:id` — детальная страница видео

**Действия:**
Настроить React Router в `src/App.jsx`

---

### Шаг 3.9: Интеграция поиска с API

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

### Шаг 3.10: Оптимизация и UX

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
- [ ] Форма добавления видео работает
- [ ] Форма редактирования видео работает
- [ ] Список видео отображается
- [ ] Валидация форм работает
- [ ] Валидация URL полей работает

### Фронтенд:
- [ ] Каталог видео отображается
- [ ] Поиск работает
- [ ] Фильтры работают
- [ ] Детальная страница видео работает
- [ ] Пагинация работает

---

## 🚀 Следующие шаги после MVP

1. Добавить аутентификацию и авторизацию
2. Добавить систему подписок
3. Добавить семантический/векторный поиск через pgvector + embeddings (опционально)
4. Добавить аналитику
5. Оптимизировать производительность
6. Добавить тесты для фронтенда и админки

