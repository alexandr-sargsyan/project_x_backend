<?php

namespace App\Models;

use App\Enums\PlatformEnum;
use App\Enums\PacingEnum;
use App\Enums\ProductionLevelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class VideoReference extends Model
{
    protected $fillable = [
        'title',
        'source_url',
        'preview_embed',
        'public_summary',
        'details_public',
        'duration_sec',
        'platform',
        'platform_video_id',
        'pacing',
        'hook_id',
        'production_level',
        'has_visual_effects',
        'has_3d',
        'has_animations',
        'has_typography',
        'has_sound_design',
        'search_profile',
        // Денормализованные поля для поиска (обновляются триггерами)
        // 'search_tags',        // Обновляется триггером при изменении тегов
        // 'search_categories',  // Обновляется триггером при изменении категорий
        'quality_score',
        'completeness_flags',
        'rating',
    ];

    /**
     * Поля, которые не должны быть в массовом присвоении
     * (обновляются триггерами PostgreSQL)
     */
    protected $guarded = [
        'search_tags',
        'search_categories',
        'search_vector',
    ];

    protected function casts(): array
    {
        return [
            'has_visual_effects' => 'boolean',
            'has_3d' => 'boolean',
            'has_animations' => 'boolean',
            'has_typography' => 'boolean',
            'has_sound_design' => 'boolean',
            'duration_sec' => 'integer',
            'hook_id' => 'integer',
            'quality_score' => 'integer',
            'rating' => 'integer',
            'completeness_flags' => 'array',
            'details_public' => 'array',
            'platform' => PlatformEnum::class,
            'pacing' => PacingEnum::class,
            'production_level' => ProductionLevelEnum::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Скрываем служебные поля от API ответов
     */
    protected $hidden = [
        'search_vector',
    ];

    /**
     * Получить категории
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'video_reference_category');
    }

    /**
     * Получить теги
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'video_reference_tag');
    }

    /**
     * Получить хук
     */
    public function hook(): BelongsTo
    {
        return $this->belongsTo(Hook::class);
    }

    /**
     * Получить tutorials (связь многие-ко-многим)
     */
    public function tutorials(): BelongsToMany
    {
        return $this->belongsToMany(Tutorial::class, 'tutorial_video_reference')
            ->withPivot('start_sec', 'end_sec')
            ->withTimestamps();
    }

    /**
     * Получить лайки
     */
    public function likes(): HasMany
    {
        return $this->hasMany(VideoReferenceLike::class);
    }

    /**
     * Получить пользователей, которые лайкнули это видео
     */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'video_reference_likes')
            ->withTimestamps();
    }

    /**
     * Получить коллекции, в которых находится это видео
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(VideoCollection::class, 'video_collection_items')
            ->withTimestamps();
    }

    /**
     * Получить количество лайков
     */
    public function getLikesCountAttribute(): int
    {
        return $this->likes()->count();
    }

    /**
     * Склеить теги в строку для full-text search
     * @deprecated Теперь используется search_tags через триггер
     */
    public function getTagsTextAttribute(): string
    {
        return $this->tags->pluck('name')->implode(' ');
    }

    /**
     * Проверить наличие tutorials
     */
    public function getHasTutorialAttribute(): bool
    {
        return $this->tutorials()->exists();
    }

    /**
     * Рассчитать quality_score
     */
    public function calculateQualityScore(): int
    {
        $score = 0;

        // Базовые очки за заполненность
        if ($this->search_profile) {
            $score += 10;
        }
        if ($this->public_summary) {
            $score += 5;
        }
        if ($this->tutorials()->exists()) {
            $score += 10;
        }
        if ($this->tags()->count() > 0) {
            $score += min($this->tags()->count() * 2, 10);
        }
        if ($this->categories()->count() > 0) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Рассчитать completeness_flags
     */
    public function calculateCompletenessFlags(): array
    {
        return [
            'has_search_profile' => !empty($this->search_profile),
            'has_public_summary' => !empty($this->public_summary),
            'has_tutorials' => $this->tutorials()->exists(),
            'tags_count' => $this->tags()->count(),
            'categories_count' => $this->categories()->count(),
        ];
    }

    /**
     * Scope для full-text search
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->whereRaw(
            "search_vector @@ to_tsquery('english', ?)",
            [$searchTerm]
        );
    }

    /**
     * Scope для нечёткого поиска (триграммы)
     */
    public function scopeFuzzySearch(Builder $query, string $searchTerm, float $threshold = 0.3): Builder
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->whereRaw("title % ?", [$searchTerm])
              ->orWhereRaw("search_tags % ?", [$searchTerm])
              ->orWhereRaw("search_categories % ?", [$searchTerm]);
        });
    }

    /**
     * Scope для фильтрации по категориям
     */
    public function scopeFilterByCategories(Builder $query, ?array $categoryIds): Builder
    {
        if (!empty($categoryIds) && is_array($categoryIds)) {
            return $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }
        return $query;
    }

    /**
     * Scope для фильтрации по platform
     */
    public function scopeFilterByPlatform(Builder $query, ?string $platform): Builder
    {
        if ($platform) {
            return $query->where('platform', $platform);
        }
        return $query;
    }

    /**
     * Scope для фильтрации по pacing
     */
    public function scopeFilterByPacing(Builder $query, ?string $pacing): Builder
    {
        if ($pacing) {
            return $query->where('pacing', $pacing);
        }
        return $query;
    }

    /**
     * Scope для фильтрации по production_level
     */
    public function scopeFilterByProductionLevel(Builder $query, ?string $productionLevel): Builder
    {
        if ($productionLevel) {
            return $query->where('production_level', $productionLevel);
        }
        return $query;
    }

    /**
     * Scope для фильтрации по has_* полям
     */
    public function scopeFilterByHasFlags(Builder $query, array $flags): Builder
    {
        foreach ($flags as $flag => $value) {
            if ($value !== null) {
                $query->where($flag, $value);
            }
        }
        return $query;
    }

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

    /**
     * Boot метод для автоматического расчёта quality_score и completeness_flags
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (VideoReference $videoReference) {
            $videoReference->quality_score = $videoReference->calculateQualityScore();
            $videoReference->completeness_flags = $videoReference->calculateCompletenessFlags();
        });
    }
}
