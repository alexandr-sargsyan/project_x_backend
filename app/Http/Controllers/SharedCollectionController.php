<?php

namespace App\Http\Controllers;

use App\Models\VideoCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedCollectionController extends Controller
{
    /**
     * Получить коллекцию и видео по share_token (публичный роут)
     */
    public function getVideos(string $token): JsonResponse
    {
        $collection = VideoCollection::where('share_token', $token)
            ->with([
                'videoReferences.categories',
                'videoReferences.tags',
                'videoReferences.tutorials'
            ])
            ->first();

        if (!$collection) {
            return response()->json([
                'message' => 'Collection not found',
            ], 404);
        }

        // Получаем видео с дополнительной информацией
        $videos = $collection->videoReferences()
            ->with(['categories', 'tags', 'tutorials'])
            ->get()
            ->map(function ($video) {
                // Добавляем базовую информацию о лайках
                $video->likes_count = $video->likes()->count();
                $video->is_liked = false; // Для публичного доступа всегда false
                return $video;
            });

        return response()->json([
            'data' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'videos' => $videos->values()->all(),
            ],
        ]);
    }
}
