<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransitionTypeRequest;
use App\Http\Requests\TransferTransitionTypeVideosRequest;
use App\Http\Requests\UpdateTransitionTypeRequest;
use App\Models\TransitionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTransitionTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TransitionType::withCount('videoReferences');

        // Поиск по name
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'ILIKE', "%{$searchTerm}%");
        }

        // Если не указана пагинация, возвращаем все элементы (для обратной совместимости)
        if (!$request->has('page') && !$request->has('per_page')) {
            $transitionTypes = $query->orderBy('name')->get();
            return response()->json([
                'data' => $transitionTypes,
            ]);
        }

        // Иначе используем пагинацию
        $perPage = $request->get('per_page', 20);
        $transitionTypes = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $transitionTypes->items(),
            'meta' => [
                'current_page' => $transitionTypes->currentPage(),
                'last_page' => $transitionTypes->lastPage(),
                'per_page' => $transitionTypes->perPage(),
                'total' => $transitionTypes->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransitionTypeRequest $request): JsonResponse
    {
        $transitionType = TransitionType::create($request->validated());

        return response()->json([
            'message' => 'Transition Type created successfully',
            'data' => $transitionType,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $transitionType = TransitionType::withCount('videoReferences')->findOrFail($id);

        return response()->json([
            'data' => $transitionType,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransitionTypeRequest $request, string $id): JsonResponse
    {
        $transitionType = TransitionType::findOrFail($id);
        $transitionType->update($request->validated());

        return response()->json([
            'message' => 'Transition Type updated successfully',
            'data' => $transitionType,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $transitionType = TransitionType::findOrFail($id);
        
        // Удаляем все связи с видео перед удалением transition type
        $transitionType->videoReferences()->detach();
        
        $transitionType->delete();

        return response()->json([
            'message' => 'Transition Type deleted successfully',
        ]);
    }

    /**
     * Transfer all videos from one transition type to another.
     */
    public function transferVideos(TransferTransitionTypeVideosRequest $request, string $id): JsonResponse
    {
        $transitionType = TransitionType::findOrFail($id);

        $validated = $request->validated();
        $targetTransitionTypeId = $validated['target_transition_type_id'];

        // Проверяем, что целевой transition type не совпадает с текущим
        if ((int)$targetTransitionTypeId === (int)$id) {
            return response()->json([
                'message' => 'Target transition type cannot be the same as source transition type',
            ], 422);
        }

        $targetTransitionType = TransitionType::findOrFail($targetTransitionTypeId);

        // Получаем все видео, связанные с текущим transition type
        $videoReferences = $transitionType->videoReferences()->get();
        $transferredCount = 0;
        $skippedCount = 0;

        foreach ($videoReferences as $video) {
            // Удаляем связь с текущим transition type
            $transitionType->videoReferences()->detach($video->id);

            // Проверяем, есть ли уже связь с целевым transition type
            if (!$video->transitionTypes()->where('transition_types.id', $targetTransitionTypeId)->exists()) {
                // Добавляем связь с целевым transition type
                $targetTransitionType->videoReferences()->attach($video->id);
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
