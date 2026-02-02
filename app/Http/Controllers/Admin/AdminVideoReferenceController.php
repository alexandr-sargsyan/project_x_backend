<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterVideoReferenceRequest;
use App\Http\Requests\StoreVideoReferenceRequest;
use App\Http\Requests\UpdateVideoReferenceRequest;
use App\Models\Tag;
use App\Models\TransitionType;
use App\Models\Tutorial;
use App\Models\VideoReference;
use App\Services\PlatformNormalizationService;
use App\Services\PostgresSearchService;
use Illuminate\Http\JsonResponse;

class AdminVideoReferenceController extends Controller
{
    public function __construct(
        private PostgresSearchService $searchService,
        private PlatformNormalizationService $normalizationService
    ) {
    }

    /**
     * Display a listing of the resource (for admin).
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

        // Для админа загружаем все связи, включая служебные поля
        $items = collect($results->items())->map(function ($videoReference) {
            // Загружаем категории если они не загружены
            if (!$videoReference->relationLoaded('categories')) {
                $videoReference->load('categories');
            }
            // Загружаем transitionTypes если они не загружены
            if (!$videoReference->relationLoaded('transitionTypes')) {
                $videoReference->load('transitionTypes');
            }
            return $videoReference;
        });

        return response()->json([
            'data' => $items,
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

        // Генерируем plain text из HTML для поиска
        if (!empty($validated['public_summary_html'])) {
            $html = $validated['public_summary_html'];
            
            // 1. Заменяем блочные элементы на пробелы (чтобы текст из разных блоков не склеивался)
            // <div>, <p>, <li>, <h1-h6>, <blockquote>, <hr> - заменяем на пробел
            $html = preg_replace('/<(div|p|li|h[1-6]|blockquote|hr)[^>]*>/i', ' ', $html);
            $html = preg_replace('/<\/(div|p|li|h[1-6]|blockquote)[^>]*>/i', ' ', $html);
            
            // 2. Заменяем <br> и <br/> на пробел
            $html = preg_replace('/<br\s*\/?>/i', ' ', $html);
            
            // 3. Удаляем все остальные теги
            $text = strip_tags($html);
            
            // 4. Конвертируем HTML entities в обычный текст (&nbsp; → пробел, &amp; → &, и т.д.)
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // 5. Нормализуем пробелы (множественные пробелы/переносы → один пробел)
            $validated['public_summary'] = preg_replace('/\s+/', ' ', trim($text));
        }

        // Нормализация URL: определяем platform и извлекаем platform_video_id
        if (!empty($validated['source_url'])) {
            $normalized = $this->normalizationService->normalizeUrl($validated['source_url']);
            
            // Если нормализация успешна, используем её данные
            if ($normalized['normalized']) {
                $validated['platform'] = $normalized['platform'];
                $validated['platform_video_id'] = $normalized['platform_video_id'];
            } else {
                // Если не удалось нормализовать, пытаемся определить platform через enum
                if (empty($validated['platform'])) {
                    $platform = PlatformEnum::fromUrl($validated['source_url']);
                    if ($platform) {
                        $validated['platform'] = $platform->value;
                    }
                }
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

        // Обрабатываем категории: получаем массив category_ids
        $categoryIds = [];
        if (!empty($validated['category_ids']) && is_array($validated['category_ids'])) {
            $categoryIds = array_filter($validated['category_ids'], fn($id) => is_numeric($id));
        }

        // Обрабатываем transition types: получаем или создаём transition types по именам
        $transitionTypeIds = [];
        if (!empty($validated['transition_types']) && is_array($validated['transition_types'])) {
            foreach ($validated['transition_types'] as $transitionTypeName) {
                $transitionTypeName = trim($transitionTypeName);
                if (empty($transitionTypeName)) {
                    continue;
                }

                // Ищем существующий transition type (case-insensitive)
                $transitionType = TransitionType::whereRaw('LOWER(name) = ?', [strtolower($transitionTypeName)])->first();

                if (!$transitionType) {
                    // Создаём новый transition type
                    $transitionType = TransitionType::create(['name' => $transitionTypeName]);
                }

                $transitionTypeIds[] = $transitionType->id;
            }
        }

        // Убираем tags, category_ids и transition_types из validated, так как будем привязывать по ID
        unset($validated['tags'], $validated['category_ids'], $validated['transition_types']);

        // Создаём видео-референс
        $videoReference = VideoReference::create($validated);

        // Привязываем категории по ID
        if (!empty($categoryIds)) {
            $videoReference->categories()->sync($categoryIds);
        }

        // Привязываем теги по ID
        if (!empty($tagIds)) {
            $videoReference->tags()->sync($tagIds);
        }

        // Привязываем transition types по ID
        if (!empty($transitionTypeIds)) {
            $videoReference->transitionTypes()->sync($transitionTypeIds);
        }

        // Обрабатываем tutorials: создаём новые или выбираем существующие
        if (!empty($validated['tutorials']) && is_array($validated['tutorials'])) {
            $tutorialSyncData = [];
            
            foreach ($validated['tutorials'] as $tutorialData) {
                $mode = $tutorialData['mode'] ?? 'new';
                $tutorialId = null;
                $pivotData = [];
                
                if ($mode === 'select') {
                    // Выбираем существующий tutorial
                    $tutorialId = $tutorialData['tutorial_id'];
                } else {
                    // Создаём новый tutorial
                    $tutorial = Tutorial::create([
                        'tutorial_url' => $tutorialData['tutorial_url'],
                        'label' => $tutorialData['label'],
                    ]);
                    $tutorialId = $tutorial->id;
                }
                
                // Добавляем pivot данные (start_sec, end_sec) если они переданы
                if (isset($tutorialData['start_sec'])) {
                    $pivotData['start_sec'] = $tutorialData['start_sec'];
                }
                if (isset($tutorialData['end_sec'])) {
                    $pivotData['end_sec'] = $tutorialData['end_sec'];
                }
                
                $tutorialSyncData[$tutorialId] = $pivotData;
            }
            
            // Синхронизируем tutorials с pivot данными
            if (!empty($tutorialSyncData)) {
                $videoReference->tutorials()->sync($tutorialSyncData);
            }
        }

        // Загружаем связи для ответа
        $videoReference->load(['categories', 'tags', 'tutorials', 'hook']);
        
        // Преобразуем tutorials, добавляя pivot данные на верхний уровень
        $videoReference->tutorials->transform(function ($tutorial) {
            if ($tutorial->pivot) {
                $tutorial->start_sec = $tutorial->pivot->start_sec;
                $tutorial->end_sec = $tutorial->pivot->end_sec;
            }
            return $tutorial;
        });

        return response()->json([
            'data' => $videoReference,
        ], 201);
    }

    /**
     * Display the specified resource (for admin).
     */
    public function show(string $id): JsonResponse
    {
        $videoReference = VideoReference::with(['categories', 'tags', 'transitionTypes', 'tutorials', 'hook'])
            ->findOrFail($id);
        
        // Преобразуем tutorials, добавляя pivot данные на верхний уровень
        $videoReference->tutorials->transform(function ($tutorial) {
            if ($tutorial->pivot) {
                $tutorial->start_sec = $tutorial->pivot->start_sec;
                $tutorial->end_sec = $tutorial->pivot->end_sec;
            }
            return $tutorial;
        });

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

        // Генерируем plain text из HTML для поиска
        if (!empty($validated['public_summary_html'])) {
            $html = $validated['public_summary_html'];
            
            // 1. Заменяем блочные элементы на пробелы (чтобы текст из разных блоков не склеивался)
            // <div>, <p>, <li>, <h1-h6>, <blockquote>, <hr> - заменяем на пробел
            $html = preg_replace('/<(div|p|li|h[1-6]|blockquote|hr)[^>]*>/i', ' ', $html);
            $html = preg_replace('/<\/(div|p|li|h[1-6]|blockquote)[^>]*>/i', ' ', $html);
            
            // 2. Заменяем <br> и <br/> на пробел
            $html = preg_replace('/<br\s*\/?>/i', ' ', $html);
            
            // 3. Удаляем все остальные теги
            $text = strip_tags($html);
            
            // 4. Конвертируем HTML entities в обычный текст (&nbsp; → пробел, &amp; → &, и т.д.)
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // 5. Нормализуем пробелы (множественные пробелы/переносы → один пробел)
            $validated['public_summary'] = preg_replace('/\s+/', ' ', trim($text));
        } elseif (isset($validated['public_summary_html']) && empty($validated['public_summary_html'])) {
            // Если HTML очищен, очищаем и plain text
            $validated['public_summary'] = null;
        }

        // Если изменился source_url, перенормализовать
        if (isset($validated['source_url']) && 
            $validated['source_url'] !== $videoReference->source_url) {
            
            $normalized = $this->normalizationService->normalizeUrl($validated['source_url']);
            
            // Если нормализация успешна, используем её данные
            if ($normalized['normalized']) {
                $validated['platform'] = $normalized['platform'];
                $validated['platform_video_id'] = $normalized['platform_video_id'];
            } else {
                // Если не удалось нормализовать, пытаемся определить platform через enum
                if (empty($validated['platform'])) {
                    $platform = PlatformEnum::fromUrl($validated['source_url']);
                    if ($platform) {
                        $validated['platform'] = $platform->value;
                    }
                }
            }
        }

        // Обрабатываем категории: получаем массив category_ids
        $categoryIds = null;
        if (isset($validated['category_ids']) && is_array($validated['category_ids'])) {
            $categoryIds = array_filter($validated['category_ids'], fn($id) => is_numeric($id));
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

        // Обрабатываем transition types: получаем или создаём transition types по именам
        $transitionTypeIds = null;
        if (array_key_exists('transition_types', $validated)) {
            $transitionTypeIds = [];
            if (is_array($validated['transition_types']) && !empty($validated['transition_types'])) {
                foreach ($validated['transition_types'] as $transitionTypeName) {
                    $transitionTypeName = trim($transitionTypeName);
                    if (empty($transitionTypeName)) {
                        continue;
                    }

                    // Ищем существующий transition type (case-insensitive)
                    $transitionType = TransitionType::whereRaw('LOWER(name) = ?', [strtolower($transitionTypeName)])->first();

                    if (!$transitionType) {
                        // Создаём новый transition type
                        $transitionType = TransitionType::create(['name' => $transitionTypeName]);
                    }

                    $transitionTypeIds[] = $transitionType->id;
                }
            }
            // Если transition_types передан (даже пустой массив), синхронизируем
        }

        // Убираем tags, category_ids и transition_types из validated, так как будем привязывать по ID
        unset($validated['tags'], $validated['category_ids'], $validated['transition_types']);

        // Обновляем видео-референс
        $videoReference->update($validated);

        // Обновляем категории если переданы
        if ($categoryIds !== null) {
            $videoReference->categories()->sync($categoryIds);
        }

        // Обновляем теги если переданы
        if ($tagIds !== null) {
            $videoReference->tags()->sync($tagIds);
        }

        // Обновляем transition types если переданы (включая пустой массив для удаления всех)
        if ($transitionTypeIds !== null) {
            $videoReference->transitionTypes()->sync($transitionTypeIds);
        }

        // Обрабатываем tutorials: всегда синхронизируем, даже если пустой массив
        // Если tutorials переданы в запросе (включая пустой массив), синхронизируем
        if (array_key_exists('tutorials', $validated)) {
            $tutorialSyncData = [];
            
            if (is_array($validated['tutorials']) && !empty($validated['tutorials'])) {
                foreach ($validated['tutorials'] as $tutorialData) {
                    $mode = $tutorialData['mode'] ?? 'new';
                    $tutorialId = null;
                    $pivotData = [];
                    
                    if ($mode === 'select') {
                        // Выбираем существующий tutorial
                        $tutorialId = $tutorialData['tutorial_id'];
                    } else {
                        // Создаём новый tutorial
                        $tutorial = Tutorial::create([
                            'tutorial_url' => $tutorialData['tutorial_url'],
                            'label' => $tutorialData['label'],
                        ]);
                        $tutorialId = $tutorial->id;
                    }
                    
                    // Добавляем pivot данные (start_sec, end_sec) если они переданы
                    if (isset($tutorialData['start_sec'])) {
                        $pivotData['start_sec'] = $tutorialData['start_sec'];
                    }
                    if (isset($tutorialData['end_sec'])) {
                        $pivotData['end_sec'] = $tutorialData['end_sec'];
                    }
                    
                    $tutorialSyncData[$tutorialId] = $pivotData;
                }
            }
            
            // Синхронизируем tutorials (если пустой массив - удалит все связи)
            $videoReference->tutorials()->sync($tutorialSyncData);
        }

        // Загружаем связи для ответа
        $videoReference->load(['categories', 'tags', 'transitionTypes', 'tutorials', 'hook']);
        
        // Преобразуем tutorials, добавляя pivot данные на верхний уровень
        $videoReference->tutorials->transform(function ($tutorial) {
            if ($tutorial->pivot) {
                $tutorial->start_sec = $tutorial->pivot->start_sec;
                $tutorial->end_sec = $tutorial->pivot->end_sec;
            }
            return $tutorial;
        });

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
