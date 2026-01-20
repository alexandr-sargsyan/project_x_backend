# Улучшения поиска - 20 января 2026

## Реализованные изменения

### 1. ✅ Изменение языка с Russian на English

**Проблема:** Использовался русский язык для английского контента, что не оптимально для морфологии.

**Решение:** 
- Обновлен `search_vector` с `to_tsvector('russian', ...)` на `to_tsvector('english', ...)`
- Обновлены все запросы в `PostgresSearchService` и `VideoReference` модели
- Изменена исходная миграция `2026_01_08_171112_create_video_references_table.php` (база еще не была в продакшене)

**Код:**
```sql
-- Старый вариант
to_tsvector('russian', ...)

-- Новый вариант
to_tsvector('english', ...)
```

---

### 2. ✅ Добавление public_summary в поиск

**Проблема:** Поле `public_summary` не индексировалось и не участвовало в поиске.

**Решение:** 
- Добавлено `public_summary` в `search_vector`
- Теперь поиск работает по: title, public_summary, search_profile, search_metadata

**Код:**
```sql
ALTER TABLE video_references 
ADD COLUMN search_vector tsvector 
GENERATED ALWAYS AS (
    to_tsvector('english', 
        coalesce(title, '') || ' ' || 
        coalesce(public_summary, '') || ' ' ||
        coalesce(search_profile, '') || ' ' || 
        coalesce(search_metadata, '')
    )
) STORED
```

---

### 3. ✅ Добавление ранжирования по релевантности

**Проблема:** Результаты не сортировались по релевантности к поисковому запросу.

**Решение:** 
- Добавлено использование `ts_rank_cd()` для ранжирования
- При поиске автоматически сортируется по `relevance_score`
- Добавлено поле `relevance_score` в результаты

**Код:**
```php
// PostgresSearchService::buildSearchQuery()
return $query
    ->whereRaw("search_vector @@ to_tsquery('english', ?)", [$tsQuery])
    ->selectRaw("video_references.*, ts_rank_cd(search_vector, to_tsquery('english', ?)) as relevance_score", [$tsQuery]);
```

**Сортировка:**
```php
// При поиске - сортировка по relevance_score
'relevance' => $hasSearchQuery 
    ? $query->orderByRaw('relevance_score DESC NULLS LAST')->orderBy('rating', 'desc')->orderBy('created_at', 'desc')
    : $query->orderBy('quality_score', 'desc')->orderBy('rating', 'desc')->orderBy('created_at', 'desc')
```

---

### 4. ✅ Гибкая логика поиска (не только AND)

**Проблема:** Слишком строгая AND логика - все слова должны присутствовать.

**Решение:** 
- Гибридная логика поиска:
  - 1 слово: просто ищется это слово
  - 2 слова: AND логика (оба слова обязательны)
  - 3+ слов: первые 2 слова AND (обязательны) + остальные OR (опционально)
- Добавлен фразовый поиск в кавычках `"exact phrase"`

**Примеры:**
```
"pizza" → pizza
"pizza commercial" → pizza & commercial
"pizza commercial restaurant" → pizza & commercial & (restaurant)
"pizza commercial restaurant food" → pizza & commercial & (restaurant | food)
"slow motion pizza" → "slow" <-> "motion" <-> "pizza" (точная фраза)
```

**Код:**
```php
protected function prepareTsQuery(string $searchTerm): string
{
    // Фразовый поиск в кавычках
    if (preg_match('/^"(.+)"$/', $searchTerm, $matches)) {
        // Используем <-> для точной фразы
        return implode(' <-> ', $words);
    }
    
    // Гибридная логика
    if (count($words) === 1) {
        return $words[0];
    } elseif (count($words) === 2) {
        return implode(' & ', $words);  // AND
    } else {
        // Первые 2 AND + остальные OR
        $firstTwo = array_slice($words, 0, 2);
        $rest = array_slice($words, 2);
        $mandatory = implode(' & ', $firstTwo);
        $optional = implode(' | ', $rest);
        return $mandatory . ' & (' . $optional . ')';
    }
}
```

---

### 5. ✅ Поддержка частичного совпадения

**Проблема:** Короткие запросы (< 3 символов) не работали с full-text search.

**Решение:** 
- Для запросов короче 3 символов используется `ILIKE` (частичное совпадение)
- Поиск по всем полям: title, search_profile, search_metadata, public_summary

**Примеры:**
```
"pi" → ILIKE '%pi%'  (найдет "pizza", "spinning", etc.)
"ab" → ILIKE '%ab%'  (найдет "abstract", "about", etc.)
```

**Код:**
```php
if (strlen($searchTerm) < 3) {
    return $query->where(function($q) use ($searchTerm) {
        $searchPattern = '%' . $searchTerm . '%';
        $q->where('title', 'ILIKE', $searchPattern)
          ->orWhere('search_profile', 'ILIKE', $searchPattern)
          ->orWhere('search_metadata', 'ILIKE', $searchPattern)
          ->orWhere('public_summary', 'ILIKE', $searchPattern);
    });
}
```

---

## Примеры использования

### Пример 1: Простой поиск
```
Запрос: "pizza"
Результат: найдутся все видео, где есть слово "pizza"
```

### Пример 2: Двухсловный поиск (AND)
```
Запрос: "pizza commercial"
Результат: найдутся видео, где есть И "pizza" И "commercial"
```

### Пример 3: Трехсловный поиск (гибридный)
```
Запрос: "pizza commercial food"
Результат: найдутся видео, где есть "pizza" И "commercial", а "food" опционально (повысит релевантность)
```

### Пример 4: Фразовый поиск
```
Запрос: "slow motion pizza"
Результат: найдутся видео, где слова "slow", "motion", "pizza" идут рядом друг с другом
```

### Пример 5: Короткий запрос
```
Запрос: "pi"
Результат: найдутся видео с "pizza", "spinning", "spiral", etc. (частичное совпадение)
```

---

## Тестирование

### Проверка через API:
```bash
# Простой поиск
curl "http://localhost:8000/api/video-references?search=pizza"

# Двухсловный поиск
curl "http://localhost:8000/api/video-references?search=pizza%20commercial"

# Трехсловный поиск
curl "http://localhost:8000/api/video-references?search=pizza%20commercial%20food"

# Фразовый поиск
curl "http://localhost:8000/api/video-references?search=\"slow%20motion%20pizza\""

# Короткий запрос
curl "http://localhost:8000/api/video-references?search=pi"
```

---

## Изменённые файлы

1. **Миграция:**
   - `database/migrations/2026_01_08_171112_create_video_references_table.php` (изменена исходная миграция)

2. **Сервисы:**
   - `app/Services/PostgresSearchService.php`
     - `buildSearchQuery()` - добавлено ранжирование и поддержка коротких запросов
     - `prepareTsQuery()` - гибридная логика и фразовый поиск
     - `applySorting()` - сортировка по relevance_score при поиске
     - `search()` - автоматический выбор сортировки

3. **Модели:**
   - `app/Models/VideoReference.php`
     - `scopeSearch()` - изменен язык на english

---

## Откат изменений (если нужно)

Изменения внесены в исходную миграцию создания таблицы, поэтому откат не требуется. Если нужно вернуться к русскому языку, нужно изменить миграцию `2026_01_08_171112_create_video_references_table.php` и пересоздать базу данных через `php artisan migrate:fresh`.

---

## Преимущества новой реализации

1. ✅ **Правильная морфология** - английский язык для английского контента
2. ✅ **Больше данных в поиске** - добавлен `public_summary`
3. ✅ **Релевантные результаты** - сортировка по `ts_rank_cd()`
4. ✅ **Гибкий поиск** - не только AND, но и гибридная логика
5. ✅ **Фразовый поиск** - поддержка точных фраз в кавычках
6. ✅ **Короткие запросы** - работают через ILIKE
7. ✅ **Лучший UX** - более интуитивные результаты поиска

---

## Что дальше?

Опционально можно добавить:
- [ ] Поддержка триграмм PostgreSQL для еще лучшего частичного совпадения
- [ ] Weighted search (разные веса для title, search_profile, etc.)
- [ ] Подсветка совпадений в результатах
- [ ] Autocomplete/suggestions на основе существующих данных
