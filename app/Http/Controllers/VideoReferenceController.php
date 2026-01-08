<?php

namespace App\Http\Controllers;

use App\Enums\PlatformEnum;
use App\Http\Requests\FilterVideoReferenceRequest;
use App\Http\Requests\StoreVideoReferenceRequest;
use App\Http\Requests\UpdateVideoReferenceRequest;
use App\Models\Tag;
use App\Models\VideoReference;
use App\Services\PostgresSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoReferenceController extends Controller
{
    public function __construct(
        private PostgresSearchService $searchService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(FilterVideoReferenceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $search = $validated['search'] ?? null;
        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        // Убираем служебные поля из фильтров
        $filters = $validated;
        unset($filters['search'], $filters['page'], $filters['per_page']);
        $filters = array_filter($filters, fn($value) => $value !== null);

        $results = $this->searchService->search($search, $filters, $perPage, $page);

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVideoReferenceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Автоматически определяем platform из URL если не указан
        if (empty($validated['platform']) && !empty($validated['source_url'])) {
            $platform = PlatformEnum::fromUrl($validated['source_url']);
            if ($platform) {
                $validated['platform'] = $platform->value;
            }
        }

        // Обрабатываем теги: получаем или создаём теги по именам
        $tagIds = [];
        if (!empty($validated['tags']) && is_array($validated['tags'])) {
            foreach ($validated['tags'] as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) {
                    continue;
                }

                // Ищем существующий тег (case-insensitive)
                $tag = Tag::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();

                if (!$tag) {
                    // Создаём новый тег
                    $tag = Tag::create(['name' => $tagName]);
                }

                $tagIds[] = $tag->id;
            }
        }

        // Убираем tags из validated, так как будем привязывать по ID
        unset($validated['tags']);

        // Создаём видео-референс
        $videoReference = VideoReference::create($validated);

        // Привязываем теги по ID
        if (!empty($tagIds)) {
            $videoReference->tags()->sync($tagIds);
        }

        // Создаём tutorials
        if (!empty($validated['tutorials'])) {
            foreach ($validated['tutorials'] as $tutorialData) {
                $videoReference->tutorials()->create($tutorialData);
            }
        }

        // Загружаем связи для ответа
        $videoReference->load(['category', 'tags', 'tutorials']);

        return response()->json([
            'data' => $videoReference,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $videoReference = VideoReference::with(['category', 'tags', 'tutorials'])
            ->findOrFail($id);

        return response()->json([
            'data' => $videoReference,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVideoReferenceRequest $request, string $id): JsonResponse
    {
        $videoReference = VideoReference::findOrFail($id);
        $validated = $request->validated();

        // Автоматически определяем platform из URL если изменился source_url
        if (isset($validated['source_url']) && empty($validated['platform'])) {
            $platform = PlatformEnum::fromUrl($validated['source_url']);
            if ($platform) {
                $validated['platform'] = $platform->value;
            }
        }

        // Обрабатываем теги: получаем или создаём теги по именам
        $tagIds = null;
        if (isset($validated['tags']) && is_array($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) {
                    continue;
                }

                // Ищем существующий тег (case-insensitive)
                $tag = Tag::whereRaw('LOWER(name) = ?', [strtolower($tagName)])->first();

                if (!$tag) {
                    // Создаём новый тег
                    $tag = Tag::create(['name' => $tagName]);
                }

                $tagIds[] = $tag->id;
            }
        }

        // Убираем tags из validated, так как будем привязывать по ID
        unset($validated['tags']);

        // Обновляем видео-референс
        $videoReference->update($validated);

        // Обновляем теги если переданы
        if ($tagIds !== null) {
            $videoReference->tags()->sync($tagIds);
        }

        // Обновляем tutorials если переданы
        if (isset($validated['tutorials'])) {
            // Удаляем старые tutorials
            $videoReference->tutorials()->delete();

            // Создаём новые
            foreach ($validated['tutorials'] as $tutorialData) {
                $videoReference->tutorials()->create($tutorialData);
            }
        }

        // Загружаем связи для ответа
        $videoReference->load(['category', 'tags', 'tutorials']);

        return response()->json([
            'data' => $videoReference,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $videoReference = VideoReference::findOrFail($id);
        $videoReference->delete();

        return response()->json([
            'message' => 'Video reference deleted successfully',
        ]);
    }
}
