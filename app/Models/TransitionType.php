<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TransitionType extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Получить все видео-референсы с этим типом перехода
     */
    public function videoReferences(): BelongsToMany
    {
        return $this->belongsToMany(VideoReference::class, 'video_reference_transition_type');
    }
}
