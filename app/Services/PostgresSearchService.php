<?php

namespace App\Services;

use App\Models\Category;
use App\Models\VideoReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PostgresSearchService
{
    /**
     * Поиск видео-референсов с фильтрами
     */
    public function search(?string $query = null, array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $builder = VideoReference::query()
            ->with(['categories', 'tags', 'tutorials', 'hook']);

        $hasSearchQuery = !empty($query);

        // Применяем full-text search если есть запрос
        if ($hasSearchQuery) {
            $builder = $this->buildSearchQuery($builder, $query);
        }

        // Применяем фильтры
        $builder = $this->buildFilters($builder, $filters);

        // Сортировка
        $sortBy = $filters['sort_by'] ?? ($hasSearchQuery ? 'relevance' : 'rating');
        $builder = $this->applySorting($builder, $sortBy, $hasSearchQuery);

        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Построить запрос с full-text search
     */
    public function buildSearchQuery(Builder $query, string $searchTerm): Builder
    {
        $searchTerm = trim($searchTerm);
        
        // Для очень коротких запросов (< 3 символов) используем ILIKE для частичного совпадения
        if (strlen($searchTerm) < 3) {
            return $query->where(function($q) use ($searchTerm) {
                $searchPattern = '%' . $searchTerm . '%';
                $q->where('title', 'ILIKE', $searchPattern)
                  ->orWhere('search_profile', 'ILIKE', $searchPattern)
                  ->orWhere('search_metadata', 'ILIKE', $searchPattern)
                  ->orWhere('public_summary', 'ILIKE', $searchPattern);
            });
        }
        
        // Преобразуем поисковый запрос в tsquery формат
        $tsQuery = $this->prepareTsQuery($searchTerm);

        // Добавляем ранжирование по релевантности через ts_rank_cd
        return $query
            ->whereRaw("search_vector @@ to_tsquery('english', ?)", [$tsQuery])
            ->selectRaw("video_references.*, ts_rank_cd(search_vector, to_tsquery('english', ?)) as relevance_score", [$tsQuery]);
    }

    /**
     * Применить фильтры через WHERE условия
     */
    public function buildFilters(Builder $query, array $filters): Builder
    {
        // Строгий фильтр по ID (точное совпадение)
        if (isset($filters['id']) && $filters['id'] !== null) {
            $query->where('id', $filters['id']);
        }

        // Строгий фильтр по source_url (точное совпадение)
        if (isset($filters['source_url']) && $filters['source_url'] !== null && $filters['source_url'] !== '') {
            $query->where('source_url', $filters['source_url']);
        }

        // Фильтр по категориям (category_ids) - логика OR (хотя бы одна из выбранных категорий)
        // Если выбрана родительская категория, автоматически включаем все её подкатегории
        if (!empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $categoryIds = array_filter($filters['category_ids'], fn($id) => is_numeric($id));
            if (!empty($categoryIds)) {
                // Находим все родительские категории из выбранных
                $parentCategories = Category::whereIn('id', $categoryIds)
                    ->whereNull('parent_id')
                    ->get();
                
                // Для каждой родительской категории получаем все дочерние (рекурсивно)
                $allCategoryIds = $categoryIds;
                foreach ($parentCategories as $parentCategory) {
                    $childIds = $this->getAllChildCategoryIds($parentCategory->id);
                    $allCategoryIds = array_merge($allCategoryIds, $childIds);
                }
                
                // Убираем дубликаты
                $allCategoryIds = array_unique($allCategoryIds);
                
                $query->whereHas('categories', function ($q) use ($allCategoryIds) {
                    $q->whereIn('categories.id', $allCategoryIds);
                });
            }
        }

        // Фильтр по platform (поддерживает массив для множественного выбора)
        if (!empty($filters['platform'])) {
            if (is_array($filters['platform'])) {
                $query->whereIn('platform', $filters['platform']);
            } else {
                $query->where('platform', $filters['platform']);
            }
        }

        // Фильтр по pacing (поддержка массива)
        if (!empty($filters['pacing'])) {
            $pacingValues = is_array($filters['pacing']) ? $filters['pacing'] : [$filters['pacing']];
            if (!empty($pacingValues)) {
                $query->whereIn('pacing', $pacingValues);
            }
        }

        // Фильтр по hook_ids (массив ID хуков, логика OR - хотя бы один из выбранных)
        if (!empty($filters['hook_ids']) && is_array($filters['hook_ids'])) {
            $hookIds = array_filter($filters['hook_ids'], fn($id) => is_numeric($id));
            if (!empty($hookIds)) {
                $query->whereIn('hook_id', $hookIds);
            }
        }

        // Фильтр по production_level (поддержка массива)
        if (!empty($filters['production_level'])) {
            $levelValues = is_array($filters['production_level']) ? $filters['production_level'] : [$filters['production_level']];
            if (!empty($levelValues)) {
                $query->whereIn('production_level', $levelValues);
            }
        }

        // Фильтры по has_* полям
        $hasFlags = [
            'has_visual_effects',
            'has_3d',
            'has_animations',
            'has_typography',
            'has_sound_design',
        ];

        foreach ($hasFlags as $flag) {
            if (isset($filters[$flag]) && $filters[$flag] !== null) {
                $query->where($flag, (bool) $filters[$flag]);
            }
        }

        // Фильтр по has_tutorial
        if (isset($filters['has_tutorial']) && $filters['has_tutorial'] !== null) {
            if ($filters['has_tutorial']) {
                $query->has('tutorials');
            } else {
                $query->doesntHave('tutorials');
            }
        }

        // Фильтр по тегам (tag_ids) - логика OR (хотя бы один из выбранных тегов)
        if (!empty($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $tagIds = array_filter($filters['tag_ids'], fn($id) => is_numeric($id));
            if (!empty($tagIds)) {
                $query->whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                });
            }
        }

        return $query;
    }

    /**
     * Применить сортировку
     * По умолчанию сортировка по рейтингу (высокий рейтинг первым)
     * При поиске - сортировка по релевантности (relevance_score)
     */
    protected function applySorting(Builder $query, string $sortBy, bool $hasSearchQuery = false): Builder
    {
        return match ($sortBy) {
            'rating' => $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc'),
            'quality_score' => $query->orderBy('quality_score', 'desc')->orderBy('rating', 'desc')->orderBy('created_at', 'desc'),
            'created_at' => $query->orderBy('created_at', 'desc')->orderBy('rating', 'desc'),
            'relevance' => $hasSearchQuery 
                ? $query->orderByRaw('relevance_score DESC NULLS LAST')->orderBy('rating', 'desc')->orderBy('created_at', 'desc')
                : $query->orderBy('quality_score', 'desc')->orderBy('rating', 'desc')->orderBy('created_at', 'desc'),
            default => $hasSearchQuery
                ? $query->orderByRaw('relevance_score DESC NULLS LAST')->orderBy('rating', 'desc')->orderBy('created_at', 'desc')
                : $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc'),
        };
    }

    /**
     * Подготовить поисковый запрос для tsquery
     * Преобразует обычный текст в формат tsquery
     * Использует гибридную логику: для 1-2 слов AND, для 3+ слов первые 2 AND + остальные OR
     */
    protected function prepareTsQuery(string $searchTerm): string
    {
        // Убираем лишние пробелы
        $searchTerm = trim($searchTerm);

        // Проверка на фразовый поиск в кавычках (точная фраза)
        if (preg_match('/^"(.+)"$/', $searchTerm, $matches)) {
            $phrase = trim($matches[1]);
            $words = preg_split('/\s+/', $phrase);
            $words = array_map(function ($word) {
                return preg_replace('/[&|!():]/', '', $word);
            }, $words);
            $words = array_filter($words, fn($word) => !empty($word));
            // Используем <-> для поиска слов рядом друг с другом (фразовый поиск)
            return implode(' <-> ', $words);
        }

        // Разбиваем на слова
        $words = preg_split('/\s+/', $searchTerm);

        // Экранируем специальные символы
        $words = array_map(function ($word) {
            // Экранируем специальные символы tsquery
            $word = preg_replace('/[&|!():]/', '', $word);
            return $word;
        }, $words);

        // Фильтруем пустые слова
        $words = array_filter($words, fn($word) => !empty($word));

        if (empty($words)) {
            return '';
        }

        // Гибридная логика для более гибкого поиска
        if (count($words) === 1) {
            // Одно слово - просто возвращаем его
            return $words[0];
        } elseif (count($words) === 2) {
            // Два слова - используем AND для точности
            return implode(' & ', $words);
        } else {
            // 3+ слов: первые 2 обязательны (AND), остальные опциональны (OR)
            $firstTwo = array_slice($words, 0, 2);
            $rest = array_slice($words, 2);
            
            // Формируем запрос: (word1 & word2) & (word3 | word4 | word5)
            $mandatory = implode(' & ', $firstTwo);
            $optional = implode(' | ', $rest);
            
            return $mandatory . ' & (' . $optional . ')';
        }
    }

    /**
     * Рекурсивно получить все дочерние категории для родительской
     */
    protected function getAllChildCategoryIds(int $parentId): array
    {
        $childIds = [];
        
        // Получаем прямых потомков
        $children = Category::where('parent_id', $parentId)->get();
        
        foreach ($children as $child) {
            $childIds[] = $child->id;
            // Рекурсивно получаем дочерние для этой категории
            $grandChildren = $this->getAllChildCategoryIds($child->id);
            $childIds = array_merge($childIds, $grandChildren);
        }
        
        return $childIds;
    }
}

