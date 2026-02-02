<?php

namespace App\Http\Controllers;

use App\Models\TransitionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransitionTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TransitionType::query();

        // Поиск по имени типа перехода
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'ILIKE', "%{$searchTerm}%");
        }

        $transitionTypes = $query->orderBy('name')->get();

        return response()->json([
            'data' => $transitionTypes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:transition_types,name'],
        ]);

        $transitionType = TransitionType::create($validated);

        return response()->json([
            'data' => $transitionType,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $transitionType = TransitionType::with('videoReferences')
            ->findOrFail($id);

        return response()->json([
            'data' => $transitionType,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $transitionType = TransitionType::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:transition_types,name,' . $id],
        ]);

        $transitionType->update($validated);

        return response()->json([
            'data' => $transitionType,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $transitionType = TransitionType::findOrFail($id);
        $transitionType->delete();

        return response()->json([
            'message' => 'Transition type deleted successfully',
        ]);
    }
}
