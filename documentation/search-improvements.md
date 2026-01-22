# Улучшения поиска - Обновлено 22 января 2026

## Обзор архитектуры поиска

Система поиска video_references использует PostgreSQL с комбинацией:
1. **Full-text search** (tsvector/tsquery) - для точного поиска с морфологией
2. **Trigram search** (pg_trgm) - для нечёткого поиска и исправления опечаток
3. **ILIKE** - для коротких запросов (< 3 символов)

---

## Реализованные улучшения

### 1. ✅ Денормализация связанных данных для поиска

**Проблема:** Теги и категории находились в отдельных таблицах и не индексировались для full-text search.

**Решение:** Добавлены денормализованные поля с автоматическим обновлением через триггеры PostgreSQL:

```sql
-- Новые поля в video_references:
search_tags TEXT DEFAULT ''       -- Денормализованные названия тегов
search_categories TEXT DEFAULT '' -- Денормализованные названия категорий
```

**Триггеры:**
- `trigger_update_search_tags_insert/delete` - обновляет `search_tags` при изменении связей с тегами
- `trigger_update_search_categories_insert/delete` - обновляет `search_categories` при изменении категорий

---

### 2. ✅ Веса для полей в search_vector

**Проблема:** Все поля имели одинаковый вес, хотя title должен быть важнее других полей.

**Решение:** Используем веса PostgreSQL (A > B > C > D):

```sql
search_vector = 
    setweight(to_tsvector('english', title), 'A') ||                    -- Самый высокий приоритет
    setweight(to_tsvector('english', search_tags), 'B') ||              -- Второй приоритет
    setweight(to_tsvector('english', search_categories), 'B') ||         -- Второй приоритет
    setweight(to_tsvector('english', search_profile), 'C') ||           -- Третий приоритет (опциональное)
    setweight(to_tsvector('english', public_summary), 'D')             -- Последний приоритет
```

---

### 3. ✅ Нечёткий поиск (Fuzzy Search) через триграммы

**Проблема:** При опечатке `"piza"` вместо `"pizza"` ничего не находилось.

**Решение:** Добавлено расширение `pg_trgm` и триграммные индексы:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX video_references_title_trgm_idx 
    ON video_references USING GIN (title gin_trgm_ops);
CREATE INDEX video_references_search_tags_trgm_idx 
    ON video_references USING GIN (search_tags gin_trgm_ops);
CREATE INDEX video_references_search_categories_trgm_idx 
    ON video_references USING GIN (search_categories gin_trgm_ops);
```

**Как работает:** Поиск объединяет full-text и триграммы:

```php
// PostgresSearchService::buildSearchQuery()
->where(function ($q) use ($tsQuery, $searchTerm) {
    // Full-text search (точные совпадения)
    $q->whereRaw("search_vector @@ to_tsquery('english', ?)", [$tsQuery]);
    
    // ИЛИ нечёткий поиск (для опечаток)
    $q->orWhere(function ($subQ) use ($searchTerm) {
        $subQ->whereRaw("title % ?", [$searchTerm])
             ->orWhereRaw("search_tags % ?", [$searchTerm])
             ->orWhereRaw("search_categories % ?", [$searchTerm]);
    });
})
```

---

### 4. ✅ Индексы для фильтров

**Проблема:** Фильтрация по platform, pacing, production_level была медленной.

**Решение:** Добавлены индексы:

```sql
CREATE INDEX idx ON video_references (platform);
CREATE INDEX idx ON video_references (pacing);
CREATE INDEX idx ON video_references (production_level);
CREATE INDEX idx ON video_references (hook_id);
CREATE INDEX idx ON video_references (rating, created_at);
CREATE INDEX idx ON video_references (created_at);

-- Составной индекс для частых комбинаций
CREATE INDEX video_references_filters_idx 
    ON video_references (platform, pacing, production_level, rating DESC);
```

---

### 5. ✅ Prefix matching для поиска

**Проблема:** Поиск `"pizz"` не находил `"pizza"`.

**Решение:** Добавлен prefix matching с `:*`:

```php
// prepareTsQuery() теперь добавляет :* к словам
"pizza" → "pizza:*"  // Найдёт pizza, pizzas, pizzeria и т.д.
```

---

### 6. ✅ Комбинированный relevance_score

**Проблема:** Релевантность учитывала только full-text, игнорируя триграммы.

**Решение:** Комбинированный score:

```sql
relevance_score = 
    CASE WHEN search_vector @@ tsquery THEN 
        ts_rank_cd(search_vector, tsquery) * 2  -- Full-text match (больший вес)
    ELSE 0 END
    +
    GREATEST(
        similarity(title, search_term),
        similarity(search_tags, search_term),
        similarity(search_categories, search_term)
    )  -- Триграммы
```

---

## Примеры использования

### Пример 1: Поиск по тегу
```
Запрос: "pizza"
Результат: Найдутся все видео с тегом "pizza" (через search_tags)
```

### Пример 2: Поиск по категории
```
Запрос: "Fashion"
Результат: Найдутся все видео из категории "Fashion" (через search_categories)
```

### Пример 3: Нечёткий поиск (опечатка)
```
Запрос: "piza" (вместо "pizza")
Результат: Найдутся видео с "pizza" благодаря триграммам
```

### Пример 4: Комбинированный поиск
```
Запрос: "fashion commercial slow"
Результат: Найдутся видео, где есть:
- слово "fashion" в title/категориях/тегах
- слово "commercial" в описании
- "slow" опционально повышает релевантность
```

---

## Тестирование через API

```bash
# Поиск по тегу
curl "http://localhost:8000/api/video-references?search=pizza"

# Нечёткий поиск (опечатка)
curl "http://localhost:8000/api/video-references?search=piza"

# Поиск по категории
curl "http://localhost:8000/api/video-references?search=Fashion"

# Фразовый поиск
curl "http://localhost:8000/api/video-references?search=\"slow%20motion\""

# Комбинированный поиск + фильтры
curl "http://localhost:8000/api/video-references?search=pizza&platform[]=youtube&pacing[]=fast"
```

---

## Изменённые файлы

### Миграции:
1. `database/migrations/2026_01_08_171112_create_video_references_table.php`
   - Добавлены поля: `search_tags`, `search_categories`
   - Обновлён `search_vector` с весами (A, B, C, D)
   - Удалены поля: `search_metadata`, `search_hook`
   - `search_profile` сделан опциональным (nullable)
   - Добавлены индексы для фильтров
   - Добавлено расширение `pg_trgm` и триграммные индексы

2. `database/migrations/2026_01_08_171113_create_video_reference_tag_table.php`
   - Добавлены триггеры для обновления `search_tags`

3. `database/migrations/2026_01_15_094122_create_video_reference_category_table.php`
   - Добавлены триггеры для обновления `search_categories`

### Сервисы:
4. `app/Services/PostgresSearchService.php`
   - `buildSearchQuery()` - гибридный поиск (full-text + триграммы)
   - `buildILikeQuery()` - поиск по всем полям включая денормализованные
   - `prepareTsQuery()` - добавлен prefix matching `:*`
   - `setTrigramThreshold()` - настройка чувствительности триграмм

### Модели:
5. `app/Models/VideoReference.php`
   - Добавлен `$guarded` для денормализованных полей
   - Добавлен `$hidden` для `search_vector`
   - Добавлен scope `scopeFuzzySearch()`
   - Обновлён `calculateCompletenessFlags()` - добавлен `categories_count`

---

## Диаграмма потока данных

```
┌─────────────────────┐
│   API Request       │
│   ?search=pizza     │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│ PostgresSearchService│
│ buildSearchQuery()  │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────────────────────────┐
│              PostgreSQL                  │
│                                          │
│  1. Full-text search (search_vector)     │
│     - title (вес A)                      │
│     - search_tags (вес B)               │
│     - search_categories (вес B)          │
│     - search_profile (вес C)             │
│     - public_summary (вес D)             │
│                                          │
│  2. Trigram search (% оператор)          │
│     - title                              │
│     - search_tags                        │
│     - search_categories                  │
│                                          │
│  3. Комбинированный relevance_score      │
└─────────────────────────────────────────┘
```

---

## Важные замечания

1. **Триггеры выполняются автоматически** - не нужно вручную обновлять `search_tags`, `search_categories`

2. **Prefix matching** - поиск `"comm"` найдёт `"commercial"`, `"community"` и т.д.

3. **Порог триграмм** - по умолчанию 0.3 (30% схожести). Можно изменить через:
   ```php
   $searchService->setTrigramThreshold(0.4); // Более строгий
   ```

4. **Производительность** - все поля имеют GIN индексы, фильтры оптимизированы составными индексами

---

## Что не реализовано (опционально в будущем)

- [ ] Autocomplete / Suggestions
- [ ] Подсветка совпадений в результатах (ts_headline)
- [ ] "Did you mean?" для исправления опечаток
- [ ] Поиск по синонимам
