<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    /**
     * Display a listing of the resource (for admin).
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('videoReferences')
            ->with(['children' => function($query) {
                $query->withCount('videoReferences')
                    ->orderBy('order', 'desc')
                    ->orderBy('name');
            }])
            ->whereNull('parent_id')
            ->orderBy('order', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:categories,slug',
                'regex:/^[a-z0-9-]+$/',
            ],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'order' => ['nullable', 'integer'],
        ]);

        // Валидация: подкатегория не может иметь свои подкатегории (максимум 1 уровень вложенности)
        if ($validated['parent_id']) {
            $parentCategory = Category::find($validated['parent_id']);
            if ($parentCategory && $parentCategory->parent_id) {
                return response()->json([
                    'message' => 'Cannot create subcategory for a subcategory. Maximum nesting level is 1.',
                ], 422);
            }
        }

        $category = Category::create($validated);

        return response()->json([
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified resource (for admin).
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::withCount('videoReferences')
            ->with(['parent', 'children' => function($query) {
                $query->withCount('videoReferences')
                    ->orderBy('order', 'desc')
                    ->orderBy('name');
            }])
            ->findOrFail($id);

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'unique:categories,slug,' . $id,
                'regex:/^[a-z0-9-]+$/',
            ],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'order' => ['sometimes', 'integer'],
        ]);

        // Валидация: подкатегория не может иметь свои подкатегории (максимум 1 уровень вложенности)
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            $parentCategory = Category::find($validated['parent_id']);
            if ($parentCategory && $parentCategory->parent_id) {
                return response()->json([
                    'message' => 'Cannot create subcategory for a subcategory. Maximum nesting level is 1.',
                ], 422);
            }
        }

        // Валидация: нельзя сделать категорию подкатегорией, если у неё уже есть подкатегории
        if (isset($validated['parent_id']) && $validated['parent_id'] && $category->children()->exists()) {
            return response()->json([
                'message' => 'Cannot convert category with children to subcategory.',
            ], 422);
        }

        $category->update($validated);

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        // Проверяем, что нет дочерних категорий
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with children',
            ], 422);
        }

        // Проверяем, что нет видео-референсов
        if ($category->videoReferences()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with video references',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
