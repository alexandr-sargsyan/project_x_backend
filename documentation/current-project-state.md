# 📊 Текущее состояние проекта: Filmmaker Reference Platform

**Дата обновления:** 2026-01-14  
**Версия:** MVP (в разработке)

---

## 📝 Последние изменения (2026-01-14)

### Интернационализация
- ✅ **Все тексты переведены на английский язык** (для американского рынка)
  - Все кнопки, заголовки, подсказки, сообщения об ошибках
  - Страницы: Profile, Collections, CollectionDetail, VideoDetail, Home
  - Компоненты: Navigation, LoginModal, RegisterModal, EmailVerificationModal, LikeButton, SaveToCollectionButton, Filters, FilterSidebar
  - Сообщения об ошибках в AuthContext

### UI/UX улучшения
- ✅ **Добавлены кнопки "Back" на всех страницах**
  - Profile: кнопка "Back" для возврата на главную
  - Collections: кнопка "Back" для возврата на главную
  - CollectionDetail: кнопка "Back" для возврата к списку каталогов
  - VideoDetail: кнопка "Back" для возврата на главную
  - Единый стиль кнопок на всех страницах

- ✅ **Добавлен логотип и название проекта в header**
  - Компонент `Logo.jsx` с синим градиентным иконкой (буква "P")
  - Название "project_x" синим цветом
  - Кликабельный элемент для перехода на главную страницу
  - Layout header: Logo (слева), SearchBar (центр), Navigation (справа)
  - Адаптивная верстка для мобильных устройств

---

## 🎯 Обзор проекта

Filmmaker Reference Platform — это платформа для поиска и каталогизации видео-референсов для видеографов, монтажёров и режиссёров. Платформа поддерживает видео с 4 платформ: **YouTube**, **TikTok**, **Instagram** и **Facebook**.

### Технологический стек

- **Backend:** Laravel 12, PHP 8.4+
- **База данных:** PostgreSQL 12+ (full-text search через tsvector/tsquery)
- **Frontend:** React 19, React Router DOM 7, TanStack Query 5
- **Admin Panel:** React 19, React Router DOM 7
- **Аутентификация:** Laravel Passport (OAuth2)

---

## 🗄️ Структура базы данных

### Таблицы

#### 1. `users`
Пользователи системы.

**Поля:**
- `id` (bigint, PK)
- `name` (string)
- `email` (string, unique)
- `password` (string, hashed)
- `email_verified_at` (timestamp, nullable)
- `remember_token` (string, nullable)
- `created_at`, `updated_at` (timestamps)

#### 2. `email_verification_codes`
Коды подтверждения email (6-значные коды).

**Поля:**
- `id` (bigint, PK)
- `email` (string, index)
- `code` (string, 6 символов)
- `expires_at` (timestamp)
- `verified_at` (timestamp, nullable)
- `created_at`, `updated_at` (timestamps)

**Индексы:**
- Уникальный индекс на `(email, code)`
- `email` (index)
- `expires_at` (index)

**Логика:**
- Код действителен 15 минут
- После подтверждения код помечается как использованный (`verified_at`)
- Можно генерировать новый код (старый помечается как неактивный)

#### 3. `oauth_*` (Laravel Passport)
Таблицы для OAuth2 аутентификации через Laravel Passport:
- `oauth_auth_codes`
- `oauth_access_tokens`
- `oauth_refresh_tokens`
- `oauth_clients`
- `oauth_device_codes`

#### 4. `categories`
Категории видео-референсов (иерархическая структура).

**Поля:**
- `id` (bigint, PK)
- `name` (string, unique)
- `slug` (string, unique)
- `parent_id` (bigint, nullable, FK → categories.id)
- `order` (integer, default 0)
- `created_at`, `updated_at` (timestamps)

#### 5. `video_references`
Основная таблица для видео-референсов.

**Display Fields (что видит пользователь):**
- `id` (bigint, PK)
- `title` (string)
- `source_url` (string)
- `preview_url` (string, nullable)
- `preview_embed` (text, nullable)
- `public_summary` (text, nullable)
- `details_public` (json, nullable)
- `duration_sec` (integer, nullable)

**Filter Fields (по чему фильтруем):**
- `category_id` (bigint, FK → categories.id)
- `platform` (string, nullable) — платформа: `youtube`, `tiktok`, `instagram`, `facebook`
- `platform_video_id` (string, nullable)
- `pacing` (string, nullable) — темп: `slow`, `fast`, `mixed`
- `hook_type` (string, nullable)
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

#### 6. `video_reference_likes`
Лайки пользователей на видео.

**Поля:**
- `id` (bigint, PK)
- `user_id` (bigint, FK → users.id, onDelete: cascade)
- `video_reference_id` (bigint, FK → video_references.id, onDelete: cascade)
- `created_at`, `updated_at` (timestamps)

**Индексы:**
- Уникальный индекс на `(user_id, video_reference_id)`
- `user_id` (index)
- `video_reference_id` (index)

#### 7. `video_collections`
Каталоги/коллекции пользователей.

**Поля:**
- `id` (bigint, PK)
- `user_id` (bigint, FK → users.id, onDelete: cascade)
- `name` (string)
- `is_default` (boolean, default false)
- `created_at`, `updated_at` (timestamps)

**Индексы:**
- `user_id` (index)
- Уникальный индекс на `(user_id, is_default)` где `is_default = true`

**Ограничения:**
- Дефолтный каталог нельзя удалять
- Дефолтный каталог нельзя переименовывать (name всегда "Избранное")

#### 8. `video_collection_items`
Связь видео с каталогами (многие-ко-многим).

**Поля:**
- `id` (bigint, PK)
- `collection_id` (bigint, FK → video_collections.id, onDelete: cascade)
- `video_reference_id` (bigint, FK → video_references.id, onDelete: cascade)
- `created_at`, `updated_at` (timestamps)

**Индексы:**
- Уникальный индекс на `(collection_id, video_reference_id)`
- `collection_id` (index)
- `video_reference_id` (index)

#### 9. `tags`
Теги для видео-референсов.

**Поля:**
- `id` (bigint, PK)
- `name` (string, unique)
- `created_at`, `updated_at` (timestamps)

#### 10. `video_reference_tag`
Pivot таблица для связи многие-ко-многим между `video_references` и `tags`.

**Поля:**
- `video_reference_id` (bigint, FK → video_references.id)
- `tag_id` (bigint, FK → tags.id)
- Уникальный индекс на `(video_reference_id, tag_id)`

#### 11. `tutorials`
Обучающие материалы (могут быть связаны с несколькими видео).

**Поля:**
- `id` (bigint, PK)
- `tutorial_url` (string, nullable)
- `label` (string, nullable)
- `created_at`, `updated_at` (timestamps)

**Валидация:** Хотя бы одно из полей (`tutorial_url` или `label`) должно быть заполнено.

#### 12. `tutorial_video_reference`
Pivot таблица для связи многие-ко-многим между `tutorials` и `video_references`.

**Поля:**
- `id` (bigint, PK)
- `tutorial_id` (bigint, FK → tutorials.id)
- `video_reference_id` (bigint, FK → video_references.id)
- `start_sec` (integer, nullable)
- `end_sec` (integer, nullable)
- `created_at`, `updated_at` (timestamps)
- Уникальный индекс на `(tutorial_id, video_reference_id)`

---

## 🔧 Backend (Laravel)

### Модели

#### `User`
**Расположение:** `app/Models/User.php`

**Связи:**
- `emailVerificationCodes()` — HasMany → EmailVerificationCode
- `likes()` — HasMany → VideoReferenceLike
- `collections()` — HasMany → VideoCollection
- `defaultCollection()` — HasOne → VideoCollection (where is_default = true)

**Методы:**
- `isEmailVerified(): bool` — проверка подтверждения email

**Traits:**
- `HasApiTokens` (Laravel Passport)

#### `EmailVerificationCode`
**Расположение:** `app/Models/EmailVerificationCode.php`

**Связи:**
- `user()` — BelongsTo → User (по email)

#### `VideoReference`
**Расположение:** `app/Models/VideoReference.php`

**Связи:**
- `category()` — BelongsTo → Category
- `tags()` — BelongsToMany → Tag (через `video_reference_tag`)
- `tutorials()` — BelongsToMany → Tutorial (через `tutorial_video_reference`, с pivot полями `start_sec`, `end_sec`)
- `likes()` — HasMany → VideoReferenceLike
- `likedByUsers()` — BelongsToMany → User (через `video_reference_likes`)

**Computed Attributes:**
- `tags_text` — склеенные теги в строку для поиска
- `has_tutorial` — проверка наличия tutorials
- `embed_url` — URL для встраивания (зависит от платформы)
- `likes_count` — количество лайков
- `is_liked` — лайкнул ли текущий пользователь (если авторизован)

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

#### `VideoReferenceLike`
**Расположение:** `app/Models/VideoReferenceLike.php`

**Связи:**
- `user()` — BelongsTo → User
- `videoReference()` — BelongsTo → VideoReference

#### `VideoCollection`
**Расположение:** `app/Models/VideoCollection.php`

**Связи:**
- `user()` — BelongsTo → User
- `videoCollectionItems()` — HasMany → VideoCollectionItem
- `videoReferences()` — BelongsToMany → VideoReference (через `video_collection_items`)

**Методы:**
- `isDefault(): bool` — проверка, является ли каталог дефолтным

#### `VideoCollectionItem`
**Расположение:** `app/Models/VideoCollectionItem.php`

**Связи:**
- `collection()` — BelongsTo → VideoCollection
- `videoReference()` — BelongsTo → VideoReference

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

#### `EmailVerificationService`
**Расположение:** `app/Services/EmailVerificationService.php`

**Назначение:** Генерация, отправка и проверка кодов подтверждения email.

**Методы:**
- `generateCode(string $email): string` — генерирует 6-значный код
- `sendVerificationCode(string $email): bool` — отправляет код на email
- `verifyCode(string $email, string $code): bool` — проверяет код
- `isCodeExpired(EmailVerificationCode $code): bool` — проверяет истечение
- `markAsVerified(string $email): void` — помечает email как подтвержденный

**Логика:**
- Код действителен 15 минут
- При генерации нового кода старые помечаются как использованные
- После подтверждения код помечается как использованный

#### `EmailService`
**Расположение:** `app/Services/EmailService.php`

**Назначение:** Отправка email-уведомлений.

**Методы:**
- `sendVerificationCode(string $email, string $code): void` — отправка кода подтверждения

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

#### `AuthController`
**Расположение:** `app/Http/Controllers/AuthController.php`

**Методы:**
- `register(RegisterRequest $request)` — POST `/api/register` — регистрация пользователя (создает пользователя, отправляет код подтверждения, создает дефолтный каталог "Избранное")
- `login(LoginRequest $request)` — POST `/api/login` — вход (проверяет email_verified_at, возвращает токен)
- `logout(Request $request)` — POST `/api/logout` — выход (отзывает токен)
- `me(Request $request)` — GET `/api/me` — получить текущего пользователя

#### `EmailVerificationController`
**Расположение:** `app/Http/Controllers/EmailVerificationController.php`

**Методы:**
- `sendCode(SendCodeRequest $request)` — POST `/api/email-verification/send-code` — отправить код на email
- `verifyCode(VerifyCodeRequest $request)` — POST `/api/email-verification/verify-code` — проверить код и подтвердить email

#### `LikeController`
**Расположение:** `app/Http/Controllers/LikeController.php`

**Методы:**
- `toggleLike(string $videoReferenceId)` — POST `/api/video-references/{id}/like` — переключить лайк (добавить/убрать)
- `checkLike(string $videoReferenceId)` — GET `/api/video-references/{id}/like` — проверить, лайкнул ли пользователь видео
- `getUserLikes(Request $request)` — GET `/api/likes` — получить все лайки текущего пользователя

#### `VideoCollectionController`
**Расположение:** `app/Http/Controllers/VideoCollectionController.php`

**Методы:**
- `index(Request $request)` — GET `/api/collections` — список всех каталогов пользователя
- `store(StoreCollectionRequest $request)` — POST `/api/collections` — создать каталог
- `show(string $id)` — GET `/api/collections/{id}` — детали каталога с видео
- `update(UpdateCollectionRequest $request, string $id)` — PUT `/api/collections/{id}` — обновить каталог (нельзя обновлять дефолтный)
- `destroy(string $id)` — DELETE `/api/collections/{id}` — удалить каталог (нельзя удалять дефолтный)

#### `VideoCollectionItemController`
**Расположение:** `app/Http/Controllers/VideoCollectionItemController.php`

**Методы:**
- `index(string $collectionId)` — GET `/api/collections/{collectionId}/videos` — список видео в каталоге
- `store(AddVideoRequest $request, string $collectionId)` — POST `/api/collections/{collectionId}/videos` — добавить видео в каталог
- `destroy(string $collectionId, string $videoReferenceId)` — DELETE `/api/collections/{collectionId}/videos/{videoId}` — удалить видео из каталога
- `checkSaved(string $videoReferenceId)` — GET `/api/video-references/{videoId}/saved` — проверить, сохранено ли видео в каталогах пользователя

#### `VideoReferenceController`
**Расположение:** `app/Http/Controllers/VideoReferenceController.php`

**Методы:**
- `index(FilterVideoReferenceRequest $request)` — GET `/api/video-references` — список с поиском и фильтрацией (включает информацию о лайках)
- `show(int $id)` — GET `/api/video-references/{id}` — детальная информация (включает информацию о лайках)
- `store(StoreVideoReferenceRequest $request)` — POST `/api/video-references` — создание
- `update(UpdateVideoReferenceRequest $request, int $id)` — PUT `/api/video-references/{id}` — обновление
- `destroy(int $id)` — DELETE `/api/video-references/{id}` — удаление

**Особенности:**
- Автоматическая нормализация URL при создании/обновлении через `PlatformNormalizationService`
- Автоматическое создание тегов по именам (case-insensitive поиск существующих)
- Поддержка many-to-many связи с tutorials (режимы "new" и "select")
- При обновлении всегда синхронизирует tutorials (даже если пустой массив — удаляет все связи)
- В ответах `index()` и `show()` включает `likes_count` и `is_liked` для авторизованных пользователей

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

#### `ProfileController`
**Расположение:** `app/Http/Controllers/ProfileController.php`

**Методы:**
- `show(Request $request)` — GET `/api/profile` — получить профиль текущего пользователя
- `update(UpdateProfileRequest $request)` — PUT `/api/profile` — обновить профиль (имя)

### Middleware

#### `EnsureEmailVerified`
**Расположение:** `app/Http/Middleware/EnsureEmailVerified.php`

**Назначение:** Проверяет, подтвержден ли email пользователя перед доступом к защищенным роутам.

**Логика:**
- Проверяет `email_verified_at` у пользователя
- Если не подтвержден, возвращает 403 с сообщением
- Применяется к защищенным роутам (кроме отправки/проверки кода)

### Mailables

#### `EmailVerificationMail`
**Расположение:** `app/Mail/EmailVerificationMail.php`

**Назначение:** Email-уведомление с кодом подтверждения.

**Шаблон:** `resources/views/emails/verification-code.blade.php`

**Содержание:**
- 6-значный код подтверждения
- Инструкция по использованию
- Время действия кода (15 минут)

### Request Validation

#### `RegisterRequest`
**Расположение:** `app/Http/Requests/RegisterRequest.php`

**Параметры:**
- `name` (required, string, max:255)
- `email` (required, string, email, max:255, unique:users)
- `password` (required, confirmed, Password::defaults())

#### `LoginRequest`
**Расположение:** `app/Http/Requests/LoginRequest.php`

**Параметры:**
- `email` (required, string, email)
- `password` (required, string)

#### `SendCodeRequest`
**Расположение:** `app/Http/Requests/SendCodeRequest.php`

**Параметры:**
- `email` (required, string, email, exists:users,email)

#### `VerifyCodeRequest`
**Расположение:** `app/Http/Requests/VerifyCodeRequest.php`

**Параметры:**
- `email` (required, string, email, exists:users,email)
- `code` (required, string, digits:6)

#### `StoreCollectionRequest` / `UpdateCollectionRequest`
**Расположение:** `app/Http/Requests/StoreCollectionRequest.php`, `app/Http/Requests/UpdateCollectionRequest.php`

**Параметры:**
- `name` (required, string, max:255)

#### `AddVideoRequest`
**Расположение:** `app/Http/Requests/AddVideoRequest.php`

**Параметры:**
- `video_reference_id` (required, integer, exists:video_references,id)

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

### Seeders

#### `UserSeeder`
**Расположение:** `database/seeders/UserSeeder.php`

**Назначение:** Создание дефолтного пользователя для разработчиков.

**Учетные данные:**
- Email: `developer@example.com`
- Password: `developer`
- Name: `Developer`
- Email подтвержден: `Да` (email_verified_at заполнен)

**Логика:**
- Проверка существования пользователя перед созданием
- После создания миграций для каталогов создает дефолтный каталог "Избранное"

---

## 🎨 Frontend (React)

### Структура компонентов

#### Страницы

**`Home.jsx`**
- Главная страница с каталогом видео
- Интегрирует `Logo`, `VideoGrid`, `SearchBar`, `CategorySidebar`, `FilterSidebar`, `Navigation`
- Layout header: Logo (слева), SearchBar (центр), Navigation (справа)
- Управляет состоянием фильтров и поиска
- Использует TanStack Query для загрузки данных
- Поддерживает модальные окна аутентификации (LoginModal, RegisterModal, EmailVerificationModal)
- Передает `onAuthRequired` в компоненты для показа модалки регистрации при попытке действия без авторизации
- Все тексты на английском языке

**`VideoDetail.jsx`**
- Детальная страница видео
- Интегрирует `VideoDetailView` и `VideoDetailSidebar`
- Кнопка "Back" для возврата на главную страницу
- Поддерживает модальные окна аутентификации
- Все тексты на английском языке

**`Profile.jsx`**
- Страница профиля пользователя
- Отображает имя и email
- Кнопка "Back" для возврата на главную страницу
- Защищена (требует авторизации)
- Все тексты на английском языке

**`Collections.jsx`**
- Список всех каталогов пользователя
- Создание, редактирование, удаление каталогов
- Кнопка "Back" для возврата на главную страницу
- Защищена (требует авторизации)
- Все тексты на английском языке

**`CollectionDetail.jsx`**
- Детальная страница каталога с видео
- Просмотр видео в каталоге
- Удаление видео из каталога
- Кнопка "Back" для возврата к списку каталогов
- Защищена (требует авторизации)
- Все тексты на английском языке

#### Компоненты аутентификации

**`AuthContext.js`**
- Контекст для управления состоянием аутентификации
- Хранение токена в localStorage
- Методы: `login`, `logout`, `register`, `sendVerificationCode`, `verifyCode`, `getCurrentUser`
- Проверка авторизации через `isAuthenticated()`

**`LoginModal.jsx`**
- Модальное окно для входа
- Валидация формы
- Обработка ошибок
- Переключение на регистрацию
- Все тексты на английском языке: "Sign In", "Password", "Don't have an account?", "Sign Up"

**`RegisterModal.jsx`**
- Модальное окно для регистрации
- Валидация формы
- После успешной регистрации автоматически открывает модалку подтверждения email
- Все тексты на английском языке: "Sign Up", "Name", "Password", "Already have an account?", "Sign In"

**`EmailVerificationModal.jsx`**
- Модальное окно для подтверждения email
- Ввод 6-значного кода
- Повторная отправка кода (с таймером)
- Поддержка `codeAlreadySent` для немедленного показа формы ввода после регистрации
- Все тексты на английском языке: "Email Verification", "Send Code", "Verify", "Didn't receive the code?", "Resend"

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
- Интегрирует `LikeButton` и `SaveToCollectionButton`
- Обработка `onAuthRequired` для показа модалки регистрации

**`VideoGrid.jsx`**
- Сетка видео-карточек
- Responsive layout
- Передает `onAuthRequired` в `VideoCard`

**`VideoDetailView.jsx`**
- Детальный вид видео
- Отображает всю информацию о видео

**`VideoDetailSidebar.jsx`**
- Боковая панель с деталями видео
- Отображает категорию, теги, tutorials, флаги
- Интегрирует `LikeButton` и `SaveToCollectionButton`

**`LikeButton.jsx`**
- Кнопка лайка с иконкой сердца
- Отображает количество лайков
- Красный цвет при лайке (Instagram-style #FF3040)
- Переключение лайка через API
- Синхронизация состояния с пропсами через `useEffect`
- Обработка `onAuthRequired` для показа модалки регистрации
- Tooltip на английском: "Sign in to like videos", "Like", "Unlike"

**`SaveToCollectionButton.jsx`**
- Кнопка сохранения в каталог
- Синий цвет при сохранении (#3b82f6)
- Модальное окно для выбора каталога
- Добавление/удаление видео из каталогов
- Проверка статуса сохранения через API
- Обработка `onAuthRequired` для показа модалки регистрации
- Тихая обработка ошибок (без alert при попытке добавить в тот же каталог)
- Все тексты на английском языке: "Save", "Save to collection", "Select Collection", "Default", "Saved", "videos", "Create Collection"

**`FilterSidebar.jsx`**
- Боковая панель с фильтрами
- **Platform:** чекбоксы (множественный выбор) — YouTube, Instagram, TikTok, Facebook
- **Pacing:** селектор — Any, Slow, Fast, Mixed
- **Production Level:** селектор — Any, Low, Mid, High
- **Tags:** поиск с множественным выбором
- **Checkboxes:** Visual Effects, 3D, Animations, Typography, Sound Design, Has Tutorial
- Кнопка "Reset Filters"
- Все тексты на английском языке

**`Filters.jsx`**
- Компонент фильтров (альтернативный вариант)
- Все тексты на английском языке: "Filters", "Category", "Platform", "Pacing", "Production Level", "All Categories", "All Platforms", "Any", "Reset Filters"

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

**`Navigation.jsx`**
- Навигация в header (справа)
- Кнопки "Sign In" и "Sign Up" (если не авторизован)
- Меню пользователя с аватаром (если авторизован)
- Поддержка модальных окон аутентификации
- Ссылки на профиль и каталоги

**`Logo.jsx`**
- Логотип и название проекта в header (слева)
- Синий градиентный иконка с буквой "P"
- Название "project_x" синим цветом
- Кликабельный элемент для перехода на главную страницу
- Title: "Go to home"

### API Service

**`api.js`**
- Централизованный сервис для всех API запросов
- Axios interceptors для автоматического добавления токена
- Обработка ошибок 401 (удаление токена)

**Методы:**

**Аутентификация:**
- `register(data)` — регистрация
- `login(data)` — вход
- `logout()` — выход
- `getCurrentUser()` — получить текущего пользователя
- `sendVerificationCode(data)` — отправить код подтверждения
- `verifyCode(data)` — проверить код подтверждения

**Видео-референсы:**
- `searchVideoReferences(query, filters)` — поиск с фильтрами
- `getVideoReference(id)` — получить видео по ID

**Категории:**
- `getCategories()` — список категорий
- `getCategory(id)` — получить категорию по ID

**Теги:**
- `getTags(search)` — список тегов с поиском

**Tutorials:**
- `tutorialsAPI.getAll()` — список всех tutorials

**Лайки:**
- `toggleLike(videoId)` — переключить лайк
- `checkLike(videoId)` — проверить лайк
- `getUserLikes()` — получить все лайки пользователя

**Каталоги:**
- `getCollections()` — получить все каталоги
- `getCollection(id)` — получить каталог по ID
- `createCollection(name)` — создать каталог
- `updateCollection(id, name)` — обновить каталог
- `deleteCollection(id)` — удалить каталог
- `getCollectionVideos(collectionId)` — получить видео в каталоге
- `addVideoToCollection(collectionId, videoId)` — добавить видео в каталог
- `removeVideoFromCollection(collectionId, videoId)` — удалить видео из каталога
- `checkVideoSaved(videoId)` — проверить, сохранено ли видео в каталогах

**Профиль:**
- `getProfile()` — получить профиль
- `updateProfile(data)` — обновить профиль

---

## 🚀 API Endpoints

### Публичные роуты (без аутентификации)

**Аутентификация:**
- `POST /api/register` — регистрация
- `POST /api/login` — вход
- `POST /api/email-verification/send-code` — отправить код подтверждения
- `POST /api/email-verification/verify-code` — проверить код подтверждения

**Контент:**
- `GET /api/video-references` — список с поиском и фильтрацией
- `GET /api/video-references/{id}` — детальная информация
- `GET /api/categories` — список всех категорий
- `GET /api/categories/{id}` — детальная информация
- `GET /api/tags?search={query}` — список тегов с поиском
- `GET /api/tutorials` — список всех tutorials

### Защищенные роуты (требуют аутентификации)

**Аутентификация:**
- `POST /api/logout` — выход
- `GET /api/me` — текущий пользователь

### Защищенные роуты (требуют аутентификации и подтверждения email)

**CRUD для контента:**
- `POST /api/video-references` — создание
- `PUT /api/video-references/{id}` — обновление
- `DELETE /api/video-references/{id}` — удаление
- `POST /api/categories` — создание
- `PUT /api/categories/{id}` — обновление
- `DELETE /api/categories/{id}` — удаление

**Лайки:**
- `POST /api/video-references/{id}/like` — переключить лайк
- `GET /api/video-references/{id}/like` — проверить лайк
- `GET /api/likes` — все лайки пользователя

**Профиль:**
- `GET /api/profile` — получить профиль
- `PUT /api/profile` — обновить профиль

**Каталоги:**
- `GET /api/collections` — список каталогов
- `POST /api/collections` — создать каталог
- `GET /api/collections/{id}` — детали каталога
- `PUT /api/collections/{id}` — обновить каталог
- `DELETE /api/collections/{id}` — удалить каталог

**Видео в каталогах:**
- `GET /api/collections/{collectionId}/videos` — список видео в каталоге
- `POST /api/collections/{collectionId}/videos` — добавить видео
- `DELETE /api/collections/{collectionId}/videos/{videoId}` — удалить видео
- `GET /api/video-references/{videoId}/saved` — проверить, сохранено ли видео в каталогах

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

### User ↔ VideoReferenceLike (One-to-Many)
- Один пользователь может лайкнуть много видео
- Один лайк принадлежит одному пользователю

### User ↔ VideoCollection (One-to-Many)
- Один пользователь может иметь много каталогов
- Один каталог принадлежит одному пользователю
- У каждого пользователя есть один дефолтный каталог "Избранное"

### VideoCollection ↔ VideoReference (Many-to-Many через VideoCollectionItem)
- Один каталог может содержать много видео
- Одно видео может быть в нескольких каталогах
- Связь через `video_collection_items`

### VideoReference ↔ VideoReferenceLike (One-to-Many)
- Одно видео может иметь много лайков
- Один лайк принадлежит одному видео

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

### Аутентификация и авторизация

**Процесс регистрации:**
1. Пользователь регистрируется (email, password, name)
2. Создается пользователь с `email_verified_at = null`
3. Автоматически создается дефолтный каталог "Избранное"
4. Отправляется код подтверждения на email
5. Пользователь вводит 6-значный код
6. После подтверждения `email_verified_at` заполняется
7. Пользователь может войти

**Процесс входа:**
1. Проверка email и password
2. Проверка `email_verified_at` (если null, возвращается 403)
3. Создание токена через Laravel Passport
4. Возврат токена и информации о пользователе

**Защита роутов:**
- Middleware `auth:api` — проверяет наличие валидного токена
- Middleware `email.verified` — проверяет подтверждение email
- Публичные роуты: просмотр контента, регистрация, вход, отправка/проверка кода
- Защищенные роуты: лайки, каталоги, профиль, CRUD контента

### Лайки

- Один пользователь может лайкнуть видео только один раз (уникальный индекс)
- При удалении видео или пользователя лайки удаляются каскадно
- Информация о лайках включается в ответы `index()` и `show()` для авторизованных пользователей
- Frontend обновляет состояние сразу после клика (оптимистичное обновление)

### Каталоги

- При регистрации автоматически создается дефолтный каталог "Избранное"
- Дефолтный каталог нельзя удалять или переименовывать
- Одно видео можно добавить в несколько каталогов
- Уникальный индекс на `(collection_id, video_reference_id)` предотвращает дубликаты
- При попытке добавить видео в каталог, где оно уже есть, ошибка обрабатывается тихо (без alert)
- При удалении каталога все видео удаляются из него каскадно

---

## 🔮 Будущие улучшения

### Планируемые функции

1. **Семантический поиск:**
   - Интеграция pgvector + embeddings для векторного поиска
   - Более точный поиск по смыслу

2. **Расширенные обучающие материалы:**
   - Более детальная структура tutorials
   - Интеграция с внешними образовательными платформами

3. **Коммуникация:**
   - Раздел для обмена референсами между клиентами и видеографами
   - Комментарии и обсуждения

4. **Сортировка по популярности:**
   - Сортировка видео по количеству лайков

---

## 📚 Дополнительная документация

- `video-player-architecture.md` — детальная архитектура видео-плееров
- `technical-implementation-plan.md` — технический план реализации
- `business-requirements.md` — бизнес-требования
- `user-authentication-implementation-plan.md` — план реализации системы пользователей

---

**Последнее обновление:** 2026-01-13
