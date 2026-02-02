<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransitionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTransitionTypeController extends Controller
{
    /**
     * Display a listing of the resource (for admin).
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
}
