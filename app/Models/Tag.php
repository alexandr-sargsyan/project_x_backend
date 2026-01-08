<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Получить все видео-референсы с этим тегом
     */
    public function videoReferences(): BelongsToMany
    {
        return $this->belongsToMany(VideoReference::class);
    }
}
