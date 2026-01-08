<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tutorial extends Model
{
    protected $fillable = [
        'video_reference_id',
        'tutorial_url',
        'label',
        'start_sec',
        'end_sec',
    ];

    protected function casts(): array
    {
        return [
            'start_sec' => 'integer',
            'end_sec' => 'integer',
        ];
    }

    /**
     * Получить видео-референс
     */
    public function videoReference(): BelongsTo
    {
        return $this->belongsTo(VideoReference::class);
    }

    /**
     * Валидация: хотя бы одно из полей должно быть заполнено
     */
    public static function boot(): void
    {
        parent::boot();

        static::saving(function (Tutorial $tutorial) {
            $hasUrl = !empty($tutorial->tutorial_url);
            $hasSegment = !empty($tutorial->label) && !empty($tutorial->start_sec) && !empty($tutorial->end_sec);

            if (!$hasUrl && !$hasSegment) {
                throw new \InvalidArgumentException('Хотя бы одно из полей должно быть заполнено: tutorial_url ИЛИ (label + start_sec + end_sec)');
            }
        });
    }
}
