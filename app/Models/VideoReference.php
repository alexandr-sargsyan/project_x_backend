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
        'preview_url',
        'preview_embed',
        'public_summary',
        'details_public',
        'duration_sec',
        'category_id',
        'platform',
        'pacing',
        'hook_type',
        'production_level',
        'has_visual_effects',
        'has_3d',
        'has_animations',
        'has_typography',
        'has_sound_design',
        'search_profile',
        'search_metadata',
        'quality_score',
        'completeness_flags',
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
            'quality_score' => 'integer',
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
     * Получить категорию
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Получить теги
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'video_reference_tag');
    }

    /**
     * Получить tutorials
     */
    public function tutorials(): HasMany
    {
        return $this->hasMany(Tutorial::class);
    }

    /**
     * Склеить теги в строку для full-text search
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
        ];
    }

    /**
     * Scope для full-text search
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->whereRaw(
            "search_vector @@ to_tsquery('russian', ?)",
            [$searchTerm]
        );
    }

    /**
     * Scope для фильтрации по категории
     */
    public function scopeFilterByCategory(Builder $query, ?int $categoryId): Builder
    {
        if ($categoryId) {
            return $query->where('category_id', $categoryId);
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
