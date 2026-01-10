<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query();

        // Поиск по имени тега
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'ILIKE', "%{$searchTerm}%");
        }

        $tags = $query->orderBy('name')->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
        ]);

        $tag = Tag::create($validated);

        return response()->json([
            'data' => $tag,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $tag = Tag::with('videoReferences')
            ->findOrFail($id);

        return response()->json([
            'data' => $tag,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:tags,name,' . $id],
        ]);

        $tag->update($validated);

        return response()->json([
            'data' => $tag,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();

        return response()->json([
            'message' => 'Tag deleted successfully',
        ]);
    }
}
