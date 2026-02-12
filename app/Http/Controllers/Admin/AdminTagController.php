<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\TransferTagVideosRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use App\Models\VideoReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::withCount('videoReferences');

        // Поиск по name
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'ILIKE', "%{$searchTerm}%");
        }

        $perPage = $request->get('per_page', 20);
        $tags = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $tags->items(),
            'meta' => [
                'current_page' => $tags->currentPage(),
                'last_page' => $tags->lastPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::create($request->validated());

        return response()->json([
            'message' => 'Tag created successfully',
            'data' => $tag,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $tag = Tag::withCount('videoReferences')->findOrFail($id);

        return response()->json([
            'data' => $tag,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTagRequest $request, string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        $tag->update($request->validated());

        return response()->json([
            'message' => 'Tag updated successfully',
            'data' => $tag,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        
        // Удаляем все связи с видео перед удалением тега
        $tag->videoReferences()->detach();
        
        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully',
        ]);
    }

    /**
     * Transfer all videos from one tag to another.
     */
    public function transferVideos(TransferTagVideosRequest $request, string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);

        $validated = $request->validated();
        $targetTagId = $validated['target_tag_id'];

        // Проверяем, что целевой тег не совпадает с текущим
        if ((int)$targetTagId === (int)$id) {
            return response()->json([
                'message' => 'Target tag cannot be the same as source tag',
            ], 422);
        }

        $targetTag = Tag::findOrFail($targetTagId);

        // Получаем все видео, связанные с текущим тегом
        $videoReferences = $tag->videoReferences()->get();
        $transferredCount = 0;
        $skippedCount = 0;

        foreach ($videoReferences as $video) {
            // Удаляем связь с текущим тегом
            $tag->videoReferences()->detach($video->id);

            // Проверяем, есть ли уже связь с целевым тегом
            if (!$video->tags()->where('tags.id', $targetTagId)->exists()) {
                // Добавляем связь с целевым тегом
                $targetTag->videoReferences()->attach($video->id);
                $transferredCount++;
            } else {
                // Пропускаем, если связь уже существует
                $skippedCount++;
            }
        }

        return response()->json([
            'message' => 'Videos transferred successfully',
            'data' => [
                'transferred_count' => $transferredCount,
                'skipped_count' => $skippedCount,
                'total_count' => $videoReferences->count(),
            ],
        ]);
    }
}
