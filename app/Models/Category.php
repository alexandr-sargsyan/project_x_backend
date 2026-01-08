<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'order',
    ];

    /**
     * Получить дочерние категории
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Получить родительскую категорию
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Получить все видео-референсы этой категории
     */
    public function videoReferences(): HasMany
    {
        return $this->hasMany(VideoReference::class);
    }

    /**
     * Проверка, является ли корневой категорией
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
