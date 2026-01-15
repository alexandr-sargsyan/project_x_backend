<?php

namespace App\Services;

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
            ->with(['categories', 'tags', 'tutorials']);

        // Применяем full-text search если есть запрос
        if ($query) {
            $builder = $this->buildSearchQuery($builder, $query);
        }

        // Применяем фильтры
        $builder = $this->buildFilters($builder, $filters);

        // Сортировка
        $sortBy = $filters['sort_by'] ?? 'quality_score';
        $builder = $this->applySorting($builder, $sortBy);

        return $builder->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Построить запрос с full-text search
     */
    public function buildSearchQuery(Builder $query, string $searchTerm): Builder
    {
        // Преобразуем поисковый запрос в tsquery формат
        $tsQuery = $this->prepareTsQuery($searchTerm);

        return $query->whereRaw(
            "search_vector @@ to_tsquery('russian', ?)",
            [$tsQuery]
        );
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
        if (!empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $categoryIds = array_filter($filters['category_ids'], fn($id) => is_numeric($id));
            if (!empty($categoryIds)) {
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
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

        // Фильтр по hook_type
        if (!empty($filters['hook_type'])) {
            $query->where('hook_type', $filters['hook_type']);
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
     */
    protected function applySorting(Builder $query, string $sortBy): Builder
    {
        return match ($sortBy) {
            'quality_score' => $query->orderBy('quality_score', 'desc')->orderBy('created_at', 'desc'),
            'created_at' => $query->orderBy('created_at', 'desc'),
            'relevance' => $query->orderBy('quality_score', 'desc')->orderBy('created_at', 'desc'),
            default => $query->orderBy('quality_score', 'desc')->orderBy('created_at', 'desc'),
        };
    }

    /**
     * Подготовить поисковый запрос для tsquery
     * Преобразует обычный текст в формат tsquery
     */
    protected function prepareTsQuery(string $searchTerm): string
    {
        // Убираем лишние пробелы
        $searchTerm = trim($searchTerm);

        // Разбиваем на слова
        $words = preg_split('/\s+/', $searchTerm);

        // Экранируем специальные символы и объединяем через &
        $words = array_map(function ($word) {
            // Экранируем специальные символы tsquery
            $word = preg_replace('/[&|!():]/', '', $word);
            return $word;
        }, $words);

        // Фильтруем пустые слова
        $words = array_filter($words, fn($word) => !empty($word));

        // Объединяем через & (AND логика)
        return implode(' & ', $words);
    }
}

